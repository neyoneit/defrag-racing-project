<?php

namespace App\Filament\Pages;

use App\Models\ServerDemo;
use App\Models\SftpCredential;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class ServerdemosBrowser extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationLabel = 'Serverdemos Browser';
    protected static ?string $navigationGroup = 'Storage';
    protected static ?string $title = 'Serverdemos Browser';
    protected static string $view = 'filament.pages.serverdemos-browser';
    protected static ?int $navigationSort = 41;

    protected const DISK = 'serverdemos';

    private const STATS_CACHE_KEY = 'serverdemos:sidebar-stats';

    // User dir currently being browsed. Empty = root (user picker).
    // Locked: only selectUser() (which verifies the name against the known
    // SFTP credentials) may set it - Livewire v3 would otherwise let the
    // client overwrite the property directly.
    #[Locked]
    public string $selectedUser = '';
    public string $search = '';
    public string $sortBy = 'mtime';   // mtime | name | size
    public string $sortDir = 'desc';
    public int $page = 1;

    /**
     * Set by wire:init in the view. It used to gate the sidebar stats, which
     * cost an SFTP traversal per credential; they are a cached query now and
     * no longer need deferring, so this only survives so the view's existing
     * wire:init has something to call.
     */
    public bool $sidebarReady = false;

    public const PER_PAGE = 50;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('serverdemos_browser') ?? false;
    }

    public function mount(): void
    {
        $this->selectedUser = '';
    }

    /** Sort keys the view offers, mapped to their column in the index. */
    private const SORT_COLUMNS = [
        'mtime'  => 'recorded_at',
        'name'   => 'filename',
        'size'   => 'size',
        'map'    => 'map_name',
        'time'   => 'time_ms',
        'player' => 'mdd_id',
    ];

    /**
     * The selected credential's demos, straight out of the `server_demos`
     * index.
     *
     * This page used to do a recursive SFTP listing on every render, cached
     * for five minutes. That worked while every demo sat on the storage VPS,
     * but it had two limits worth naming: it broke the moment a file was no
     * longer local, and searching or sorting meant pulling a whole
     * credential's listing - tens of thousands of entries - into memory
     * first. `serverdemos:index` keeps the table current, so filtering,
     * sorting and paging are the database's job now.
     */
    private function query()
    {
        $query = ServerDemo::query()->where('owner_dir', $this->selectedUser);

        $needle = trim($this->search);
        if ($needle !== '') {
            $query->where('filename', 'like', '%' . $needle . '%');
        }

        return $query;
    }

    public function initSidebar(): void
    {
        $this->sidebarReady = true;
    }

    // Sidebar: every active SFTP credential plus its totals. One grouped
    // query over the index replaces what used to be a full SFTP traversal per
    // credential, so this no longer has to be deferred behind wire:init - the
    // flag stays only because the view still sets it.
    public function getCredentialsProperty(): array
    {
        $creds = SftpCredential::with('user:id,name,plain_name,mdd_id')
            ->where('status', 'active')
            ->get();

        $stats = $this->sidebarStats();

        return $creds->map(function ($c) use ($stats) {
            $user = $c->sftp_username;
            $stat = $stats[$user] ?? null;

            $count = (int) ($stat['n'] ?? 0);
            $bytes = (int) ($stat['bytes'] ?? 0);
            $last = isset($stat['last']) ? Carbon::parse($stat['last'])->getTimestamp() : null;

            return [
                'sftp_username' => $user,
                'owner_name'    => $c->user?->plain_name ?: ($c->user?->name ?: '?'),
                'owner_id'      => $c->user_id,
                'count'         => $count,
                'last'          => $last,
                'bytes'         => $bytes,
            ];
        })->sortByDesc('last')->values()->all();
    }

    /**
     * Per-credential totals, cached because Livewire re-renders the whole
     * page on every keystroke in the search box, every sort and every page
     * turn - and this aggregate costs ~600 ms over the full index while its
     * result only moves when serverdemos:index runs. Cleared by the refresh
     * button.
     */
    private function sidebarStats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, 300, fn () => ServerDemo::selectRaw(
            'owner_dir, count(*) as n, sum(size) as bytes, max(recorded_at) as last'
        )->groupBy('owner_dir')->get()->keyBy('owner_dir')->map(fn ($r) => [
            'n' => (int) $r->n,
            'bytes' => (int) $r->bytes,
            'last' => $r->last,
        ])->all());
    }

    // Main pane: the current page of files for the selected user, with the
    // demo's mdd id resolved to a player - the scraped mDd nick, and a
    // defrag.racing profile link when an account is paired to that mdd id.
    // Resolved per page (50 rows), so it's cheap.
    public function getFilesProperty(): array
    {
        if ($this->selectedUser === '') {
            return [];
        }

        $column = self::SORT_COLUMNS[$this->sortBy] ?? 'recorded_at';

        $rows = $this->query()
            ->orderBy($column, $this->sortDir === 'asc' ? 'asc' : 'desc')
            ->forPage($this->page, self::PER_PAGE)
            ->get()
            ->map(fn (ServerDemo $demo) => [
                'path'   => $demo->path,
                'name'   => $demo->filename,
                'size'   => $demo->size,
                'mtime'  => $demo->recorded_at?->getTimestamp(),
                'map'    => $demo->map_name,
                'time'   => $demo->time_ms,
                'player' => $demo->mdd_id,
            ])
            ->all();

        $mddIds = array_values(array_unique(array_filter(array_column($rows, 'player'))));
        if ($mddIds) {
            $profiles = \App\Models\MddProfile::whereIn('id', $mddIds)
                ->get(['id', 'plain_name'])
                ->keyBy('id');
            $users = \App\Models\User::whereIn('mdd_id', $mddIds)
                ->get(['id', 'mdd_id', 'plain_name', 'name'])
                ->keyBy('mdd_id');

            foreach ($rows as &$r) {
                $mdd = $r['player'];
                $user = $mdd ? $users->get($mdd) : null;
                $profile = $mdd ? $profiles->get($mdd) : null;
                $r['player_name'] = $user?->plain_name
                    ?: ($user?->name ? preg_replace('/\^[0-9A-Za-z]/', '', $user->name) : null)
                    ?: $profile?->plain_name;
                $r['player_user_id'] = $user?->id;
            }
        }

        return $rows;
    }

    public function getTotalFilesProperty(): int
    {
        return $this->selectedUser === '' ? 0 : $this->query()->count();
    }

    public function getTotalPagesProperty(): int
    {
        return max(1, (int) ceil($this->totalFiles / self::PER_PAGE));
    }

    public function nextPage(): void
    {
        $this->page = min($this->totalPages, $this->page + 1);
    }

    public function prevPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    // Filenames are parsed once, by the indexer - App\Services\ServerDemoPath.

    public function selectUser(string $user): void
    {
        // Normalize + guard: refuse anything that isn't a known SFTP credential.
        $known = SftpCredential::where('sftp_username', $user)->exists();
        if (!$known) {
            Notification::make()->title('Unknown user')->danger()->send();
            return;
        }
        $this->selectedUser = $user;
        $this->search = '';
        $this->page = 1;
    }

    public function goRoot(): void
    {
        $this->selectedUser = '';
        $this->search = '';
        $this->page = 1;
    }

    public function setSort(string $col): void
    {
        if (!in_array($col, ['name', 'size', 'mtime', 'map', 'time', 'player'], true)) return;
        if ($this->sortBy === $col) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $col;
            $this->sortDir = $col === 'mtime' ? 'desc' : 'asc';
        }
        $this->page = 1;
    }

    // Drops the cached sidebar totals and says how fresh the index behind
    // them is - a demo uploaded since the last index run is not listed yet,
    // and no amount of refreshing here will change that.
    public function refreshStats(): void
    {
        Cache::forget(self::STATS_CACHE_KEY);

        $last = ServerDemo::max('indexed_at');

        Notification::make()
            ->title('Listing comes from the demo index')
            ->body($last
                ? 'Last indexed ' . Carbon::parse($last)->diffForHumans() . '. Runs nightly, or `php artisan serverdemos:index`.'
                : 'The index is empty - run `php artisan serverdemos:index`.')
            ->success()
            ->send();
    }

    // Livewire can't reliably carry a streamed download itself (a hand-built
    // StreamedResponse isn't recognised - the button silently did nothing on
    // prod), so hand the browser a short-lived signed url instead. The
    // controller re-checks auth + this panel's permission and streams the
    // file SFTP -> HTTP with no local temp file anywhere.
    public function downloadFile(string $relPath)
    {
        if ($this->selectedUser === '') return null;

        // Hard-confine the relPath to the selected user's subtree. The prefix
        // check alone can be defeated with 'user/../other/x', so traversal and
        // degenerate segments are rejected too (demo names contain brackets,
        // so only the segment shape is constrained, not the character set).
        // Backslash is rejected too: Flysystem rewrites '\' to '/' BEFORE
        // resolving '..', so 'user/..\other' would otherwise pass this loop
        // and the prefix check, then traverse out of the user's subtree.
        $relPath = ltrim($relPath, '/');
        foreach (explode('/', $relPath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || str_contains($segment, '\\') || str_contains($segment, "\0")) {
                Notification::make()->title('Forbidden')->danger()->send();
                return null;
            }
        }
        if (!str_starts_with($relPath, $this->selectedUser . '/')) {
            Notification::make()->title('Forbidden')->danger()->send();
            return null;
        }

        return redirect()->to(\Illuminate\Support\Facades\URL::temporarySignedRoute(
            'defraghq.storage-download',
            now()->addMinutes(5),
            ['disk' => self::DISK, 'path' => $relPath],
        ));
    }
}
