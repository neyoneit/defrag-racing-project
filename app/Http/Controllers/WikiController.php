<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WikiPage;
use App\Models\WikiRevision;
use App\Models\WikiBan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WikiController extends Controller
{
    public function index()
    {
        $pages = WikiPage::whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('title')])
            ->get(['id', 'slug', 'title', 'parent_id', 'sort_order']);

        $isStaff = auth()->check() && (auth()->user()->hasModeratorPermission('wiki'));

        $wikiModerators = User::where(function ($q) {
                $q->where('admin', true)
                  ->orWhere(function ($q2) {
                      $q2->where('is_moderator', true)
                         ->where('moderator_permissions', 'like', '%wiki%');
                  });
            })->get(['id', 'name', 'username', 'profile_photo_path', 'admin']);

        return Inertia::render('Wiki/Index', [
            'pages' => $pages,
            'isStaff' => $isStaff,
            'wikiModerators' => $wikiModerators,
        ]);
    }

    public function show(string $slug)
    {
        $page = WikiPage::where('slug', $slug)
            ->with(['creator:id,name,username', 'updater:id,name,username', 'parent:id,slug,title'])
            ->firstOrFail();

        $children = $page->children()->orderBy('title')->get(['id', 'slug', 'title', 'sort_order']);

        $navigation = WikiPage::whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('title')->select(['id', 'slug', 'title', 'parent_id', 'sort_order'])])
            ->get(['id', 'slug', 'title', 'parent_id', 'sort_order']);

        // Build siblings + prev/next for sub-pages (alphabetical)
        $siblings = collect();
        $prevPage = null;
        $nextPage = null;
        if ($page->parent_id) {
            $siblings = WikiPage::where('parent_id', $page->parent_id)
                ->orderBy('title')
                ->get(['id', 'slug', 'title', 'sort_order']);
            $idx = $siblings->search(fn ($s) => $s->id === $page->id);
            if ($idx !== false) {
                $prevPage = $idx > 0 ? $siblings[$idx - 1] : null;
                $nextPage = $idx < $siblings->count() - 1 ? $siblings[$idx + 1] : null;
            }
        }

        $canEdit = false;
        $isStaff = false;
        if (auth()->check()) {
            $user = auth()->user();
            $isStaff = $user->hasModeratorPermission('wiki');
            $canEdit = !$page->is_locked || $isStaff;
            if (!$isStaff && WikiBan::where('user_id', $user->id)->exists()) {
                $canEdit = false;
            }
        }

        $revisionCount = $page->revisions()->count();

        return Inertia::render('Wiki/Show', [
            'page' => $page,
            'children' => $children,
            'navigation' => $navigation,
            'siblings' => $siblings,
            'prevPage' => $prevPage,
            'nextPage' => $nextPage,
            'revisionCount' => $revisionCount,
            'canEdit' => $canEdit,
            'isStaff' => $isStaff,
        ]);
    }

    public function edit(string $slug)
    {
        $page = WikiPage::where('slug', $slug)->firstOrFail();

        $user = auth()->user();
        $isStaff = $user->hasModeratorPermission('wiki');

        if ($page->is_locked && !$isStaff) {
            abort(403, 'This page is locked.');
        }

        if (!$isStaff && WikiBan::where('user_id', $user->id)->exists()) {
            abort(403, 'You are banned from editing the wiki.');
        }

        $parents = WikiPage::whereNull('parent_id')
            ->where('id', '!=', $page->id)
            ->orderBy('sort_order')
            ->get(['id', 'title']);

        return Inertia::render('Wiki/Edit', [
            'page' => $page,
            'parents' => $parents,
            'isStaff' => $isStaff,
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $page = WikiPage::where('slug', $slug)->firstOrFail();
        $user = auth()->user();
        $isStaff = $user->hasModeratorPermission('wiki');

        if ($page->is_locked && !$isStaff) {
            abort(403, 'This page is locked.');
        }

        if (!$isStaff && WikiBan::where('user_id', $user->id)->exists()) {
            abort(403, 'You are banned from editing the wiki.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'summary' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:wiki_pages,id'],
            'is_locked' => ['nullable', 'boolean'],
        ]);

        // Only create revision if content or title actually changed
        if ($page->content !== $validated['content'] || $page->title !== $validated['title']) {
            $page->createRevision($user->id, $validated['summary'] ?? null);
        }

        $data = [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'parent_id' => $isStaff ? ($validated['parent_id'] ?? $page->parent_id) : $page->parent_id,
            'is_locked' => $isStaff ? ($validated['is_locked'] ?? $page->is_locked) : $page->is_locked,
            'updated_by' => $user->id,
        ];

        if ($isStaff) {
            $data['keywords'] = $validated['keywords'] ?? $page->keywords;
        }

        $page->update($data);

        return redirect()->route('wiki.show', $page->slug);
    }

    public function create()
    {
        $user = auth()->user();
        if (!$user->hasModeratorPermission('wiki')) {
            abort(403);
        }

        $parents = WikiPage::whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'title']);

        return Inertia::render('Wiki/Create', [
            'parents' => $parents,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasModeratorPermission('wiki')) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:wiki_pages,slug', 'regex:/^[a-z0-9-]+$/'],
            'content' => ['required', 'string'],
            'parent_id' => ['nullable', 'exists:wiki_pages,id'],
        ]);

        $page = WikiPage::create([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        WikiRevision::create([
            'wiki_page_id' => $page->id,
            'user_id' => $user->id,
            'content' => $validated['content'],
            'summary' => 'Initial creation',
        ]);

        return redirect()->route('wiki.show', $page->slug);
    }

    public function history(string $slug)
    {
        $page = WikiPage::where('slug', $slug)->firstOrFail();

        $revisions = $page->revisions()
            ->with('user:id,name,username')
            ->paginate(50);

        return Inertia::render('Wiki/History', [
            'page' => $page->only('id', 'slug', 'title'),
            'revisions' => $revisions,
            'isStaff' => auth()->check() && (auth()->user()->hasModeratorPermission('wiki')),
            'isAdmin' => auth()->check() && auth()->user()->isAdmin(),
        ]);
    }

    public function revision(string $slug, WikiRevision $revision)
    {
        $page = WikiPage::where('slug', $slug)->firstOrFail();

        if ($revision->wiki_page_id !== $page->id) {
            abort(404);
        }

        $revision->load('user:id,name,username');

        // Revision = snapshot BEFORE the edit. To show the diff, we need the state AFTER.
        // "After" = the next revision's content, or the current page content if this is the latest revision.
        $nextRevision = WikiRevision::where('wiki_page_id', $page->id)
            ->where('id', '>', $revision->id)
            ->orderBy('id')
            ->first();

        $afterContent = $nextRevision ? $nextRevision->content : $page->content;

        $navigation = WikiPage::whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('title')->select(['id', 'slug', 'title', 'parent_id', 'sort_order'])])
            ->get(['id', 'slug', 'title', 'parent_id', 'sort_order']);

        return Inertia::render('Wiki/Revision', [
            'page' => $page->only('id', 'slug', 'title'),
            'revision' => $revision,
            'afterContent' => $afterContent,
            'navigation' => $navigation,
            'isStaff' => auth()->check() && (auth()->user()->hasModeratorPermission('wiki')),
        ]);
    }

    public function revert(Request $request, string $slug, WikiRevision $revision)
    {
        $user = auth()->user();
        if (!$user->hasModeratorPermission('wiki')) {
            abort(403);
        }

        $page = WikiPage::where('slug', $slug)->firstOrFail();

        if ($revision->wiki_page_id !== $page->id) {
            abort(404);
        }

        // Save current state as revision
        $page->createRevision($user->id, "Reverted to revision #{$revision->id}");

        $page->update([
            'title' => $revision->title,
            'content' => $revision->content,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('wiki.show', $page->slug);
    }

    public function deleteRevision(string $slug, WikiRevision $revision)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            abort(403);
        }

        $page = WikiPage::where('slug', $slug)->firstOrFail();
        if ($revision->wiki_page_id !== $page->id) {
            abort(404);
        }

        $revision->update(['deleted_by' => $user->id]);
        $revision->delete();

        return redirect()->route('wiki.history', $slug);
    }

    public function destroy(string $slug)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            abort(403);
        }

        $page = WikiPage::where('slug', $slug)->firstOrFail();
        $page->delete();

        return redirect()->route('wiki.index');
    }

    // Admin: ban/unban users from wiki editing
    public function ban(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasModeratorPermission('wiki')) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        WikiBan::updateOrCreate(
            ['user_id' => $validated['user_id']],
            ['banned_by' => $user->id, 'reason' => $validated['reason'] ?? null]
        );

        return back();
    }

    public function unban(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasModeratorPermission('wiki')) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        WikiBan::where('user_id', $validated['user_id'])->delete();

        return back();
    }

    public function toggleLock(string $slug)
    {
        $user = auth()->user();
        if (!$user->hasModeratorPermission('wiki')) {
            abort(403);
        }

        $page = WikiPage::where('slug', $slug)->firstOrFail();
        $page->update(['is_locked' => !$page->is_locked]);

        return back();
    }

    // Global wiki history - all changes across all pages
    public function globalHistory()
    {
        $revisions = WikiRevision::with(['user:id,name,username', 'page:id,slug,title'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return Inertia::render('Wiki/GlobalHistory', [
            'revisions' => $revisions,
            'isStaff' => auth()->check() && (auth()->user()->hasModeratorPermission('wiki')),
        ]);
    }

    // API: all pages with full content (for normalization)
    // Upload image for wiki pages (file upload or download from URL)
    public function uploadImage(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $dir = public_path('images/wiki');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Option 1: File upload
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => ['required', 'image', 'max:5120'],
            ]);

            $file = $request->file('image');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $file->move($dir, $filename);

            return response()->json(['url' => '/images/wiki/' . $filename]);
        }

        // Option 2: Download from URL
        if ($request->has('url')) {
            $request->validate([
                'url' => ['required', 'url'],
            ]);

            $url = $request->input('url');

            try {
                $contents = file_get_contents($url, false, stream_context_create([
                    'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0'],
                    'ssl' => ['verify_peer' => false],
                ]));

                if (!$contents) {
                    return response()->json(['error' => 'Failed to download image'], 422);
                }

                // Detect extension from content type or URL
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($contents);
                $extMap = [
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
                    'image/webp' => 'webp', 'image/svg+xml' => 'svg',
                ];
                $ext = $extMap[$mime] ?? null;

                if (!$ext) {
                    return response()->json(['error' => 'Not a valid image format'], 422);
                }

                if (strlen($contents) > 5 * 1024 * 1024) {
                    return response()->json(['error' => 'Image too large (max 5MB)'], 422);
                }

                $filename = time() . '_' . substr(md5($url), 0, 8) . '.' . $ext;
                file_put_contents($dir . '/' . $filename, $contents);

                return response()->json(['url' => '/images/wiki/' . $filename]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to download: ' . $e->getMessage()], 422);
            }
        }

        return response()->json(['error' => 'No image or URL provided'], 422);
    }

    // Normalize a page's HTML content (re-save through TipTap format)
    // Reorder top-level wiki pages (staff only)
    public function reorder(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasModeratorPermission('wiki')) {
            abort(403);
        }

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:wiki_pages,id'],
        ]);

        foreach ($validated['order'] as $index => $id) {
            WikiPage::where('id', $id)->update(['sort_order' => $index]);
        }

        return back();
    }

    // API: lightweight search index (all pages, for client-side fuzzy search)
    public function searchIndex()
    {
        $pages = WikiPage::with('parent:id,title,slug')
            ->get(['id', 'slug', 'title', 'parent_id', 'keywords', 'content']);

        return response()->json($pages->map(function ($page) {
            $plain = strip_tags($page->content);
            return [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'parent' => $page->parent ? ['title' => $page->parent->title] : null,
                'keywords' => $page->keywords ?? '',
                'snippet' => mb_substr($plain, 0, 200),
            ];
        }));
    }

    // API: search wiki pages
    public function search(Request $request)
    {
        $query = trim($request->get('q', ''));
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $pages = WikiPage::with('parent:id,title,slug')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('keywords', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'slug', 'title', 'parent_id', 'content']);

        $lowerQuery = mb_strtolower($query);

        $results = $pages->map(function ($page) use ($lowerQuery) {
            // Strip HTML tags for snippet extraction
            $plainContent = strip_tags($page->content);
            $snippet = null;

            // Find the query position in plain text content
            $pos = mb_stripos($plainContent, $lowerQuery);
            if ($pos !== false) {
                $start = max(0, $pos - 60);
                $end = min(mb_strlen($plainContent), $pos + mb_strlen($lowerQuery) + 60);
                $snippet = ($start > 0 ? '...' : '') .
                    mb_substr($plainContent, $start, $end - $start) .
                    ($end < mb_strlen($plainContent) ? '...' : '');
            } else {
                // No content match - show beginning of content
                $snippet = mb_substr($plainContent, 0, 120) . (mb_strlen($plainContent) > 120 ? '...' : '');
            }

            // Score: title > keywords > content
            $titleMatch = mb_stripos($page->title, $lowerQuery) !== false;
            $keywordMatch = $page->keywords && mb_stripos($page->keywords, $lowerQuery) !== false;

            return [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'parent' => $page->parent ? ['title' => $page->parent->title, 'slug' => $page->parent->slug] : null,
                'snippet' => $snippet,
                'score' => ($titleMatch ? 2 : 0) + ($keywordMatch ? 1 : 0),
            ];
        })
        ->sortByDesc('score')
        ->values();

        return response()->json($results);
    }
}
