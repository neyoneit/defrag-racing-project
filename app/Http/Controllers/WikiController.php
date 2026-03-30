<?php

namespace App\Http\Controllers;

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
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->get(['id', 'slug', 'title', 'parent_id', 'sort_order']);

        return Inertia::render('Wiki/Index', [
            'pages' => $pages,
        ]);
    }

    public function show(string $slug)
    {
        $page = WikiPage::where('slug', $slug)
            ->with(['creator:id,name,username', 'updater:id,name,username', 'parent:id,slug,title'])
            ->firstOrFail();

        $children = $page->children()->get(['id', 'slug', 'title', 'sort_order']);

        $navigation = WikiPage::whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->select(['id', 'slug', 'title', 'parent_id', 'sort_order'])])
            ->get(['id', 'slug', 'title', 'parent_id', 'sort_order']);

        $canEdit = false;
        $isStaff = false;
        if (auth()->check()) {
            $user = auth()->user();
            $isStaff = $user->isAdmin() || $user->isModerator();
            $canEdit = !$page->is_locked || $isStaff;
            if (!$isStaff && WikiBan::where('user_id', $user->id)->exists()) {
                $canEdit = false;
            }
        }

        return Inertia::render('Wiki/Show', [
            'page' => $page,
            'children' => $children,
            'navigation' => $navigation,
            'canEdit' => $canEdit,
            'isStaff' => $isStaff,
        ]);
    }

    public function edit(string $slug)
    {
        $page = WikiPage::where('slug', $slug)->firstOrFail();

        $user = auth()->user();
        $isStaff = $user->isAdmin() || $user->isModerator();

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
        $isStaff = $user->isAdmin() || $user->isModerator();

        if ($page->is_locked && !$isStaff) {
            abort(403, 'This page is locked.');
        }

        if (!$isStaff && WikiBan::where('user_id', $user->id)->exists()) {
            abort(403, 'You are banned from editing the wiki.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'summary' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:wiki_pages,id'],
            'is_locked' => ['nullable', 'boolean'],
        ]);

        // Create revision before updating
        $page->createRevision($user->id, $validated['summary'] ?? null);

        $page->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'parent_id' => $isStaff ? ($validated['parent_id'] ?? $page->parent_id) : $page->parent_id,
            'is_locked' => $isStaff ? ($validated['is_locked'] ?? $page->is_locked) : $page->is_locked,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('wiki.show', $page->slug);
    }

    public function create()
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isModerator()) {
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
        if (!$user->isAdmin() && !$user->isModerator()) {
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
            'isStaff' => auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isModerator()),
        ]);
    }

    public function revision(string $slug, WikiRevision $revision)
    {
        $page = WikiPage::where('slug', $slug)->firstOrFail();

        if ($revision->wiki_page_id !== $page->id) {
            abort(404);
        }

        $revision->load('user:id,name,username');

        $navigation = WikiPage::whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->select(['id', 'slug', 'title', 'parent_id', 'sort_order'])])
            ->get(['id', 'slug', 'title', 'parent_id', 'sort_order']);

        return Inertia::render('Wiki/Revision', [
            'page' => $page->only('id', 'slug', 'title'),
            'revision' => $revision,
            'navigation' => $navigation,
            'isStaff' => auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isModerator()),
        ]);
    }

    public function revert(Request $request, string $slug, WikiRevision $revision)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isModerator()) {
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
        if (!$user->isAdmin() && !$user->isModerator()) {
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
        if (!$user->isAdmin() && !$user->isModerator()) {
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
        if (!$user->isAdmin() && !$user->isModerator()) {
            abort(403);
        }

        $page = WikiPage::where('slug', $slug)->firstOrFail();
        $page->update(['is_locked' => !$page->is_locked]);

        return back();
    }

    // API: search wiki pages
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $pages = WikiPage::where('title', 'like', "%{$query}%")
            ->orWhere('content', 'like', "%{$query}%")
            ->limit(20)
            ->get(['id', 'slug', 'title']);

        return response()->json($pages);
    }
}
