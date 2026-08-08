<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\DownloadFile;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DownloadsController extends Controller
{
    private const PER_PAGE = 30;

    private const UPLOAD_DISK = 'community';

    /**
     * The browsable categories that render as an article instead of a table.
     * Each holds a handful of curated entries where the question is not "which
     * of these thirty" but "what is this and which one do I take", which a
     * table cannot answer. They live under Bundles and repacks in the tree but
     * are pages in their own right, and the sidebar hoists them out of it.
     *
     * Useful PK3s is deliberately absent: it is a long list of unrelated
     * single-purpose pk3s, which is exactly what a table is for.
     */
    private const SHELVES = [
        'game-bundles' => [
            'intro' => 'A bundle is the whole thing in one download: the game files DeFRaG needs, the mod itself, an engine that runs it and a config that works out of the box. Start here if you have nothing installed yet.',
            'notes' => [
                'Take the 64bit build unless the machine is genuinely old.',
                'Already playing? The quick upgrade replaces the mod, engine and configs without pulling the whole game down again.',
            ],
            // The multi-part entry is the one most people came for, so it is
            // lifted out of the list and its parts become the buttons.
            'feature_parts' => true,
        ],
        'repacks' => [
            'intro' => 'Repacks are content in bulk: the maps, textures and shaders of a whole category in a handful of pk3s, instead of downloading them one map at a time. Unpack them into your baseq3 folder.',
            'notes' => [
                'The map packs are bsp only - geometry without artwork - so they need the texture pack of the same category to look right.',
                'Last rebuilt in 2020. Anything released since still comes down per map.',
            ],
            'feature_parts' => false,
            'group_by' => 'gametype',
        ],
        'upscaled-textures' => [
            'intro' => 'The stock artwork redrawn at a higher resolution. Cosmetic only - geometry, physics and timing are untouched, so a run made with these is an ordinary run.',
            'notes' => [
                'These are large. On a weak GPU they cost more than they give.',
            ],
            'feature_parts' => false,
        ],
    ];

    /**
     * Capped by docker/php.ini (upload_max_filesize + post_max_size = 100M).
     * MAX_POST_MB is what the whole request may weigh, so several files have
     * to fit inside it together. Raising these means raising the ini first.
     */
    private const MAX_FILE_MB = 95;
    private const MAX_POST_MB = 100;

    /**
     * Anything a pk3 can hold, plus the archives people ship them in. No
     * executables: this list is a whitelist for that reason.
     */
    private const ALLOWED_EXTENSIONS = [
        'pk3', 'zip', '7z', 'rar', 'tar', 'gz',
        'cfg', 'txt', 'shader', 'menu', 'arena', 'skin', 'md3', 'map', 'aas', 'bsp',
        'tga', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'pcx',
        'wav', 'ogg', 'mp3', 'roq', 'dat',
    ];

    /**
     * The hub listing: category tree on the left, downloads table on the right.
     *
     * The route keeps its legacy `/downloads/{id}/{slug}` shape. Pre-hub links
     * carry ids from the old bundle_categories table, which no longer line up
     * with anything, so the slug is resolved first and the id is only a
     * fallback. The old slugs were built in JS as name.toLowerCase() with
     * spaces dashed, which matches the Str::slug() the migration used, so
     * every old link still lands on the right category.
     */
    public function index(Request $request, $id = null, $slug = null)
    {
        $category = $this->resolveCategory($id, $slug);

        if ($category && isset(self::SHELVES[$category->slug])) {
            return Inertia::render('Downloads/Index', [
                'tree' => $this->tree(),
                'current' => $this->currentPayload($category),
                'panel' => $this->shelfPanel($category, self::SHELVES[$category->slug]),
                'downloads' => null,
                'filters' => ['q' => '', 'sort' => 'newest', 'defrag' => false],
                'totalCount' => Download::published()->count(),
            ]);
        }

        // A locked category is not a listing: it renders as its own article
        // that keeps itself up to date, so it skips the table entirely.
        if ($category && $category->auto_source) {
            return Inertia::render('Downloads/Index', [
                'tree' => $this->tree(),
                'current' => $this->currentPayload($category),
                'panel' => $this->panel($category),
                'downloads' => null,
                'filters' => ['q' => '', 'sort' => 'newest', 'defrag' => false],
                'totalCount' => Download::published()->count(),
            ]);
        }

        $search = trim((string) $request->input('q', ''));
        $sort = $request->input('sort', 'newest');
        $defragOnly = $request->boolean('defrag');

        // total_size stays 0 for entries that only carry an external_url, where
        // the bytes live on someone else's host and the listing shows a dash.
        $query = Download::published()
            ->with(['user:id,name', 'category:id,name,slug'])
            ->withCount('files')
            ->withSum('files as total_size', 'size');

        if ($category) {
            $query->whereIn('category_id', $category->descendantIds());
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($defragOnly) {
            $query->where('defrag_only', true);
        }

        match ($sort) {
            'popular' => $query->orderByDesc('downloads_count')->orderByDesc('id'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $downloads = $query->paginate(self::PER_PAGE)->withQueryString();
        $downloads->setCollection($this->foldNumberedParts($downloads->getCollection()));

        return Inertia::render('Downloads/Index', [
            'tree' => $this->tree(),
            'current' => $category ? $this->currentPayload($category) : null,
            'panel' => null,
            'downloads' => $downloads,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
                'defrag' => $defragOnly,
            ],
            'totalCount' => Download::published()->count(),
        ]);
    }

    /**
     * Upload form. Locked categories are filtered out of the picker, so the
     * auto-managed articles cannot be posted into.
     */
    public function create()
    {
        return Inertia::render('Downloads/Upload', [
            'categories' => $this->selectableCategories(),
            'maxFileMb' => self::MAX_FILE_MB,
            'maxTotalMb' => self::MAX_POST_MB,
            'allowedExtensions' => self::ALLOWED_EXTENSIONS,
        ]);
    }

    public function store(Request $request)
    {
        $extensions = implode(',', self::ALLOWED_EXTENSIONS);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:download_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'defrag_only' => ['boolean'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            // extensions, not mimes: mimes validates the content-sniffed type,
            // and binary Quake formats (md3, bsp, aas, roq, dat) all sniff as
            // octet-stream, so mimes would reject every one of them.
            'files.*' => ['file', "extensions:{$extensions}", 'max:' . (self::MAX_FILE_MB * 1024)],
            'screenshots' => ['nullable', 'array', 'max:6'],
            'screenshots.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $category = DownloadCategory::findOrFail($data['category_id']);

        // Belt and braces: the picker already hides these, but a handcrafted
        // POST must not slip an upload into an auto-managed article.
        if ($category->is_locked || $category->auto_source) {
            return back()->withErrors(['category_id' => 'That category is managed automatically and does not take uploads.']);
        }

        $user = $request->user();

        $download = Download::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) ?: 'upload',
            'description' => $data['description'] ?? null,
            'youtube_url' => $data['youtube_url'] ?? null,
            'defrag_only' => $request->boolean('defrag_only'),
            'status' => Download::STATUS_PUBLISHED,
            'is_locked' => false,
        ]);

        $stored = [];

        try {
            foreach ($request->file('files') as $position => $file) {
                $stored[] = $this->storeUpload($download, $file, DownloadFile::KIND_FILE, $position);
            }

            foreach ($request->file('screenshots') ?? [] as $position => $shot) {
                $stored[] = $this->storeUpload($download, $shot, DownloadFile::KIND_SCREENSHOT, $position);
            }
        } catch (\Throwable $e) {
            // Never leave a half-uploaded entry behind: drop the objects that
            // did land, then the row itself.
            foreach ($stored as $path) {
                Storage::disk(self::UPLOAD_DISK)->delete($path);
            }

            $download->delete();

            Log::error('Download upload failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['files' => 'Upload failed: ' . $e->getMessage()]);
        }

        return redirect()
            ->route('downloads.show', [$download, $download->slug])
            ->with('success', 'Upload published.');
    }

    /**
     * Puts one uploaded file on the community bucket under the uploader's own
     * folder and records it. Returns the stored path so a failed upload can be
     * rolled back.
     */
    private function storeUpload(Download $download, $file, string $kind, int $position): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = "community/{$download->user_id}/{$download->id}/" . Str::ulid() . ".{$extension}";

        // putFileAs streams from the temp file; ->get() would hold the whole
        // upload (up to 95MB) as a string inside the Swoole worker.
        Storage::disk(self::UPLOAD_DISK)->putFileAs(dirname($path), $file, basename($path));

        DownloadFile::create([
            'download_id' => $download->id,
            'kind' => $kind,
            'disk' => self::UPLOAD_DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'extension' => $extension,
            'position' => $position,
        ]);

        return $path;
    }

    /**
     * One shelf as its own article: a lead paragraph saying what the thing is,
     * the notes worth knowing before downloading, an optional featured entry,
     * and the rest grouped.
     */
    private function shelfPanel(DownloadCategory $category, array $config): array
    {
        $items = $this->foldNumberedParts(
            Download::published()
                ->whereIn('category_id', $category->descendantIds())
                ->withCount('files')
                ->withSum('files as total_size', 'size')
                ->orderBy('position')
                ->orderBy('name')
                ->get()
        )->map(fn (Download $d) => [
            'id' => $d->id,
            'slug' => $d->slug,
            'name' => $d->name,
            'description' => $d->description,
            'size' => (int) $d->total_size,
            'downloads_count' => (int) $d->downloads_count,
            'external_url' => $d->external_url,
            'parts' => is_array($d->parts) ? $d->parts : null,
        ]);

        // The featured entry is the one that came in parts: on this shelf that
        // is the all-in-one, and its parts are the platform choice, which is
        // the only decision the page exists to help with.
        $feature = null;

        if (($config['feature_parts'] ?? false)) {
            $feature = $items->firstWhere('parts', '!=', null);
            $items = $items->reject(fn ($i) => $feature && $i['id'] === $feature['id']);
        }

        $groups = [];

        if (($config['group_by'] ?? null) === 'gametype') {
            foreach ($items as $item) {
                $group = $this->gametypeOf($item['name']);
                $groups[$group][] = $item;
            }

            // Fixed order rather than whatever the listing happened to return,
            // so Run is first on a site that is mostly run.
            $order = ['Run', 'Fastcaps', 'Freestyle', 'Levelshots', 'Other'];
            $groups = collect($order)
                ->filter(fn ($name) => ! empty($groups[$name]))
                ->map(fn ($name) => ['name' => $name, 'items' => $groups[$name]])
                ->values()
                ->all();
        } elseif ($items->isNotEmpty()) {
            $groups = [['name' => $feature ? 'Also here' : null, 'items' => $items->values()->all()]];
        }

        return [
            'type' => 'shelf',
            'intro' => $config['intro'],
            'notes' => $config['notes'] ?? [],
            'feature' => $feature,
            'groups' => $groups,
        ];
    }

    /**
     * Which side of the game a repack belongs to, read off its name. The 2020
     * repacks are named by gametype, and a fastcaps player wants that shelf's
     * maps and its textures together - not every map pack on the page.
     */
    private function gametypeOf(string $name): string
    {
        return match (true) {
            (bool) preg_match('/^levelshots/i', $name) => 'Levelshots',
            (bool) preg_match('/^fastcaps/i', $name) => 'Fastcaps',
            (bool) preg_match('/^freestyle/i', $name) => 'Freestyle',
            (bool) preg_match('/^run\b/i', $name) => 'Run',
            default => 'Other',
        };
    }

    /**
     * Folds "RUN textures 1", "RUN textures 2", "RUN textures 3" into one row
     * carrying a download button per part.
     *
     * The 2020 repacks are split only because a single pk3 that size is
     * unwieldy; the parts are not different things and nobody wants one of
     * them. Listed separately they filled the category with near-identical
     * rows - four of them just for RUN maps - and pushed everything else off
     * the screen. The number moves out of the name and onto the buttons.
     *
     * Folding happens within the rendered page only, so a set straddling a
     * page boundary shows as two rows instead of one row that quietly hides
     * where its other half went. A number that is part of a word (32bit) or a
     * year (last update 2020) is not a part number and is left alone.
     */
    private function foldNumberedParts(EloquentCollection $items): EloquentCollection
    {
        $parsed = $items->map(fn (Download $d) => $this->partOf($d))->all();

        $seen = [];
        foreach ($parsed as $part) {
            if ($part) {
                $seen[$part['key']] = ($seen[$part['key']] ?? 0) + 1;
            }
        }

        $rows = [];
        $anchorIndex = [];

        foreach ($items->values() as $i => $download) {
            $part = $parsed[$i];

            // A lone "... 1" is not a set - it keeps its number and its row.
            if (! $part || ($seen[$part['key']] ?? 0) < 2) {
                $rows[] = $download;
                continue;
            }

            $entry = [
                'id' => $download->id,
                'slug' => $download->slug,
                'label' => $part['label'],
                'sort' => $part['sort'],
                'external_url' => $download->external_url,
                'size' => (int) $download->total_size,
            ];

            if (! isset($anchorIndex[$part['key']])) {
                $download->name = $part['name'];
                $download->setAttribute('parts', [$entry]);
                $anchorIndex[$part['key']] = count($rows);
                $rows[] = $download;
                continue;
            }

            $anchor = $rows[$anchorIndex[$part['key']]];
            $anchor->setAttribute('parts', array_merge($anchor->parts, [$entry]));
            $anchor->total_size = (int) $anchor->total_size + (int) $download->total_size;
            $anchor->downloads_count = (int) $anchor->downloads_count + (int) $download->downloads_count;
        }

        // Whatever order the listing was sorted in, the parts themselves read
        // as 1, 2, 3 - or Windows, Linux, macOS.
        foreach ($rows as $row) {
            if (is_array($row->parts)) {
                $parts = $row->parts;
                usort($parts, fn ($a, $b) => $a['sort'] <=> $b['sort']);
                $row->setAttribute('parts', $parts);
            }
        }

        return new EloquentCollection($rows);
    }

    /**
     * Reads a name as "<set> <part><rest>" under either rule:
     *
     *   "RUN maps 2 (bsp) - last update 2020"   -> set "RUN maps (bsp) ...", part 2
     *   "DeFRaG Bundle all-in-one Linux 32bit"  -> set "DeFRaG Bundle all-in-one",
     *                                              part "Linux 32"
     *
     * Returns null when the name carries neither, which is most of them.
     */
    private function partOf(Download $download): ?array
    {
        $fold = function (string $set, string $rest, string $label, int $sort) use ($download): ?array {
            $set = rtrim($set);

            if ($set === '') {
                return null;
            }

            return [
                'key' => $download->category_id . '|' . $set . '|' . $rest,
                'label' => $label,
                'name' => $set . $rest,
                'sort' => $sort,
            ];
        };

        // Platform builds of one release. Ordered the way people pick: the
        // system most of them are on, newest word size first.
        if (preg_match('/^(.*?)\s(windows|linux|mac(?:os)?|osx)\s*(32|64)\s*bit(\b.*)$/iu', $download->name, $m)) {
            $rank = ['windows' => 0, 'linux' => 1, 'mac' => 2, 'macos' => 2, 'osx' => 2];
            $system = strtolower($m[2]);

            return $fold(
                $m[1],
                $m[4],
                ucfirst($system === 'osx' ? 'macOS' : $system) . ' ' . $m[3],
                ($rank[$system] ?? 9) * 10 + ($m[3] === '64' ? 0 : 1)
            );
        }

        // A plain part number. The \b after the digits keeps 32bit and "last
        // update 2020" out - neither has a boundary where the number ends.
        if (preg_match('/^(.*?)\s(\d{1,2})(\b.*)$/u', $download->name, $m)) {
            return $fold($m[1], $m[3], $m[2], (int) $m[2]);
        }

        return null;
    }

    /**
     * The flat category list for the upload picker: every category a person is
     * allowed to post into, with its full path so "Music" is not ambiguous.
     */
    private function selectableCategories(): array
    {
        $categories = DownloadCategory::orderBy('position')->orderBy('name')->get()->keyBy('id');

        return $categories
            ->filter(fn ($c) => ! $c->is_locked && ! $c->auto_source)
            ->map(function ($category) use ($categories) {
                $path = [];

                for ($node = $category; $node; $node = $node->parent_id ? $categories->get($node->parent_id) : null) {
                    array_unshift($path, $node->name);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'path' => implode(' / ', $path),
                    'depth' => count($path) - 1,
                ];
            })
            ->sortBy('path')
            ->values()
            ->all();
    }

    /**
     * Detail page: description, screenshot gallery, YouTube embed, file list.
     */
    public function show(Request $request, Download $download)
    {
        if ($download->status !== Download::STATUS_PUBLISHED) {
            $user = $request->user();
            $owner = $user && $user->id === $download->user_id;

            // A hidden entry stays reachable for its author and for staff, so
            // moderation is reviewable without flipping it public first.
            if (! $owner && ! ($user && $this->isStaff($user))) {
                abort(404);
            }
        }

        $download->load(['user:id,name,country', 'category:id,name,slug,parent_id', 'files', 'screenshots']);

        return Inertia::render('Downloads/Show', [
            'download' => [
                'id' => $download->id,
                'name' => $download->name,
                'slug' => $download->slug,
                'description' => $download->description,
                'youtube_id' => $download->youtube_id,
                'external_url' => $download->external_url,
                'is_locked' => $download->is_locked,
                'status' => $download->status,
                'defrag_only' => $download->defrag_only,
                'downloads_count' => $download->downloads_count,
                'created_at' => $download->created_at,
                'user' => $download->user,
                'category' => $download->category ? [
                    'id' => $download->category->id,
                    'name' => $download->category->name,
                    'slug' => $download->category->slug,
                    'breadcrumb' => $this->breadcrumb($download->category),
                ] : null,
                'files' => $download->files->map(fn ($f) => [
                    'id' => $f->id,
                    'original_name' => $f->original_name,
                    'size' => $f->size,
                    'extension' => $f->extension,
                    'url' => route('downloads.file', $f),
                ]),
                'screenshots' => $download->screenshots->map(fn ($f) => [
                    'id' => $f->id,
                    'url' => $f->viewUrl(),
                ]),
            ],
            // Editing happens in Filament for now; there is no Inertia edit
            // page yet, so only staff get a link and it points at the admin.
            'canModerate' => $request->user() && $this->isStaff($request->user()),
        ]);
    }

    /**
     * Serves one file.
     *
     * On S3 the browser is redirected to a short-lived signed link: the bucket
     * is private, so a public URL would 401, and signing keeps the bytes off
     * the app server, which matters for the bigger pk3 packs. Local disks have
     * no signing and no public URL, so there the file is streamed instead,
     * which is what dev runs on.
     */
    public function file(DownloadFile $file)
    {
        $download = $file->download;

        if (! $download || $download->status !== Download::STATUS_PUBLISHED) {
            abort(404);
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            Log::error('Download file missing from storage', [
                'download_file_id' => $file->id,
                'disk' => $file->disk,
                'path' => $file->path,
            ]);

            abort(404);
        }

        // Counting the parent rather than the file keeps a multi-file entry
        // from inflating its own number.
        $download->increment('downloads_count');

        if (config("filesystems.disks.{$file->disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl($file->path, now()->addMinutes(15)));
        }

        return $disk->download($file->path, $file->original_name);
    }

    private function isStaff($user): bool
    {
        // Already true for admins.
        return $user->hasModeratorPermission('downloads');
    }

    private function currentPayload(DownloadCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_locked' => $category->is_locked,
            'auto_source' => $category->auto_source,
            'breadcrumb' => $this->breadcrumb($category),
        ];
    }

    /**
     * The article payload for a locked category. Everything here comes from a
     * sync command, so a stale or empty result means the sync has not run yet
     * rather than that something is broken.
     */
    private function panel(DownloadCategory $category): array
    {
        $entries = Download::published()
            ->where('category_id', $category->id)
            ->orderBy('position')
            ->get();

        if ($category->auto_source === DownloadCategory::SOURCE_DEFRAG_MOD) {
            $byChannel = $entries->groupBy(fn ($d) => $d->meta['channel'] ?? 'stable');

            $shape = fn ($d) => [
                'id' => $d->id,
                'version' => $d->meta['version'] ?? '?',
                'date' => $d->meta['date'] ?? null,
                'size' => $d->meta['size'] ?? null,
                'filename' => $d->meta['filename'] ?? null,
                'url' => $d->external_url,
                'is_latest' => (bool) ($d->meta['is_latest'] ?? false),
            ];

            // Newest first: version_compare beats a string sort, which would
            // put 1.9 above 1.91.
            $sort = fn ($items) => $items
                ->sort(fn ($a, $b) => version_compare($b['version'], $a['version']))
                ->values()
                ->all();

            $stable = $sort(($byChannel['stable'] ?? collect())->map($shape));
            $beta = $sort(($byChannel['beta'] ?? collect())->map($shape));

            return [
                'type' => 'defrag_mod',
                'stable' => $stable,
                'beta' => $beta,
                'latest' => collect($stable)->firstWhere('is_latest', true) ?? ($stable[0] ?? null),
                'source_url' => \App\Services\DefragReleaseScraper::STABLE_URL,
            ];
        }

        if ($category->auto_source === DownloadCategory::SOURCE_FAMILY_FRIENDLY) {
            return [
                'type' => 'family_friendly',
                'files' => $entries->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'description' => $d->description,
                    'filename' => $d->meta['filename'] ?? null,
                    'size' => $d->meta['size'] ?? null,
                    'updated_at' => $d->meta['updated_at'] ?? null,
                    'url' => $d->external_url,
                ])->values()->all(),
            ];
        }

        if ($category->auto_source === DownloadCategory::SOURCE_SERVER_BUNDLE) {
            $archives = $entries->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'description' => $d->description,
                'filename' => $d->meta['filename'] ?? null,
                // Older rows were published before the split and are all Linux.
                'platform' => $d->meta['platform'] ?? 'linux',
                'size' => $d->meta['size'] ?? null,
                'url' => $d->external_url,
            ])->values();

            // Two separate installs, not one page with a footnote: a repo of
            // its own, its own core archive and its own build stamp, because
            // the two cores are built from separate inputs and can sit a
            // release apart. Only baseq3 belongs to both.
            $meta = fn (string $platform) => $entries
                ->first(fn ($d) => ($d->meta['platform'] ?? null) === $platform)?->meta ?? [];

            $filesFor = fn (string $platform) => $archives
                ->filter(fn ($a) => in_array($a['platform'], [$platform, 'both'], true))
                ->values()
                ->all();

            $platform = fn (array $spec) => $spec + [
                'archives' => $filesFor($spec['key']),
                'built_at' => $meta($spec['key'])['built_at'] ?? null,
                'engine' => $meta($spec['key'])['engine'] ?? null,
                'mod' => $meta($spec['key'])['mod'] ?? null,
            ];

            return [
                'type' => 'server_bundle',
                'platforms' => [
                    $platform([
                        'key' => 'linux',
                        'name' => 'Linux',
                        'summary' => 'Docker is the recommended route and needs Compose v2; a native systemd setup is documented as well. Around 2 GB of disk, 150 MB of RAM per server.',
                        'repo_url' => 'https://github.com/Defrag-racing/defrag-server-bundle',
                        'install' => "git clone https://github.com/Defrag-racing/defrag-server-bundle.git ./dfsv\ncd dfsv\n./download_defrag.sh",
                    ]),
                    $platform([
                        'key' => 'windows',
                        'name' => 'Windows',
                        'summary' => 'One Windows service per server, so they come back after a reboot or a crash. Demos upload themselves every 30 minutes and the map pool syncs locally, no NFS involved.',
                        'repo_url' => 'https://github.com/Defrag-racing/defrag-server-bundle-windows',
                        'install' => "git clone https://github.com/Defrag-racing/defrag-server-bundle-windows.git C:\\dfsv\ncd C:\\dfsv\nSet-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope LocalMachine -Force\n.\\scripts\\download-defrag.ps1",
                    ]),
                ],
            ];
        }

        return ['type' => 'unknown'];
    }

    private function resolveCategory($id, $slug): ?DownloadCategory
    {
        if ($slug) {
            $category = DownloadCategory::where('slug', $slug)->first();

            if ($category) {
                return $category;
            }
        }

        if (is_numeric($id) && (int) $id > 0) {
            return DownloadCategory::find((int) $id);
        }

        return null;
    }

    private function breadcrumb(DownloadCategory $category): array
    {
        $crumbs = [];

        for ($node = $category; $node; $node = $node->parent) {
            array_unshift($crumbs, [
                'id' => $node->id,
                'name' => $node->name,
                'slug' => $node->slug,
            ]);
        }

        return $crumbs;
    }

    /**
     * The whole tree with per-node counts. A parent's count includes its
     * descendants, so "Maps 124" means the branch holds 124 downloads rather
     * than 124 sitting directly on the parent.
     */
    private function tree(): array
    {
        $categories = DownloadCategory::orderBy('position')->orderBy('name')->get();

        $direct = Download::published()
            ->select('category_id', DB::raw('count(*) as aggregate'))
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $byParent = $categories->groupBy('parent_id');

        $build = function ($parentId) use (&$build, $byParent, $direct) {
            return $byParent->get($parentId, collect())->map(function ($category) use ($build, $direct) {
                $children = $build($category->id);

                $count = (int) ($direct[$category->id] ?? 0)
                    + collect($children)->sum('count');

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'is_locked' => $category->is_locked,
                    'auto_source' => $category->auto_source,
                    'count' => $count,
                    'children' => $children,
                ];
            })->values()->all();
        };

        return $build(null);
    }
}
