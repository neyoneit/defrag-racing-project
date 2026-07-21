<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\DownloadFile;
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
                'size' => $d->meta['size'] ?? null,
                'url' => $d->external_url,
            ])->values()->all();

            $marker = $entries->first()?->meta ?? [];

            return [
                'type' => 'server_bundle',
                'archives' => $archives,
                'built_at' => $marker['built_at'] ?? null,
                'engine' => $marker['engine'] ?? null,
                'mod' => $marker['mod'] ?? null,
                'repo_url' => 'https://github.com/Defrag-racing/defrag-server-bundle',
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
