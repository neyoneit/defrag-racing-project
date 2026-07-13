<?php

namespace App\Filament\Pages;

use App\Models\SftpCredential;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ServerdemosBrowser extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationLabel = 'Serverdemos Browser';
    protected static ?string $navigationGroup = 'Storage';
    protected static ?string $title = 'Serverdemos Browser';
    protected static string $view = 'filament.pages.serverdemos-browser';
    protected static ?int $navigationSort = 41;

    protected const DISK = 'serverdemos';

    // User dir currently being browsed. Empty = root (user picker).
    public string $selectedUser = '';
    public string $search = '';
    public string $sortBy = 'mtime';   // mtime | name | size
    public string $sortDir = 'desc';
    public int $page = 1;

    /**
     * The sidebar stats need a full SFTP traversal per credential, which over
     * WAN takes seconds on a cold cache - too slow to block the initial page
     * render on. The page paints immediately with placeholders and wire:init
     * flips this to load the stats in a follow-up request.
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

    protected function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * One cached recursive listing per user dir. The old code called size()
     * and lastModified() per file - one SFTP round trip EACH, so a server
     * with hundreds of demos took ages to open. Flysystem's listContents()
     * already carries size + mtime in the directory listing itself, so this
     * is a single traversal; 60s cache makes search/sort/paging instant.
     */
    private function listing(string $user): array
    {
        return Cache::remember("serverdemos:list:{$user}", 300, function () use ($user) {
            $rows = [];
            // listContents() is lazy - connection/listing errors surface during
            // iteration, so the whole loop sits inside the try.
            try {
                foreach ($this->disk()->getDriver()->listContents($user, true) as $item) {
                    if (! $item->isFile()) continue;
                    $name = basename($item->path());
                    if (!str_ends_with(strtolower($name), '.dm_68')) continue;
                    $parsed = $this->parseDemoFilename($name);
                    $rows[] = [
                        'path'  => $item->path(),
                        'name'  => $name,
                        'size'  => $item->fileSize(),
                        'mtime' => $item->lastModified(),
                        'map'   => $parsed['map'],
                        'time'  => $parsed['time'],
                        'player'=> $parsed['player'],
                    ];
                }
            } catch (\Throwable) {
                return [];
            }
            return $rows;
        });
    }

    public function initSidebar(): void
    {
        $this->sidebarReady = true;
    }

    // Sidebar: every active SFTP credential + live stats, derived from the
    // same cached listing the file pane uses (no separate traversal). Until
    // wire:init flips $sidebarReady, only the credential names render (stats
    // null -> placeholders) so the initial page load never touches SFTP.
    public function getCredentialsProperty(): array
    {
        $creds = SftpCredential::with('user:id,name,plain_name,mdd_id')
            ->where('status', 'active')
            ->get();

        return $creds->map(function ($c) {
            $user = $c->sftp_username;
            $last = null;
            $bytes = null;
            $count = null;
            if ($this->sidebarReady) {
                $files = $this->listing($user);
                $count = count($files);
                $bytes = 0;
                foreach ($files as $f) {
                    $bytes += (int) ($f['size'] ?? 0);
                    if ($f['mtime'] && (!$last || $f['mtime'] > $last)) $last = $f['mtime'];
                }
            }

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

    // Main pane: the current page of files for the selected user, with the
    // demo's mdd id (second bracket of the filename) resolved to a player -
    // the scraped mDd nick, and a defrag.racing profile link when an account
    // is paired to that mdd id. Resolved per page (50 rows), so it's cheap.
    public function getFilesProperty(): array
    {
        $rows = array_slice(
            $this->filteredRows(),
            ($this->page - 1) * self::PER_PAGE,
            self::PER_PAGE
        );

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
        return count($this->filteredRows());
    }

    public function getTotalPagesProperty(): int
    {
        return max(1, (int) ceil($this->totalFiles / self::PER_PAGE));
    }

    private function filteredRows(): array
    {
        if ($this->selectedUser === '') return [];

        $rows = $this->listing($this->selectedUser);

        $needle = strtolower(trim($this->search));
        if ($needle !== '') {
            $rows = array_values(array_filter(
                $rows,
                fn ($r) => str_contains(strtolower($r['name']), $needle)
            ));
        }

        $dir = $this->sortDir === 'asc' ? 1 : -1;
        usort($rows, function ($a, $b) use ($dir) {
            $av = $a[$this->sortBy] ?? null;
            $bv = $b[$this->sortBy] ?? null;
            if ($av === $bv) return 0;
            return ($av < $bv ? -1 : 1) * $dir;
        });

        return $rows;
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

    // defrag-server-bundle filename pattern: <map>[<time>][<mddId>].dm_68
    // e.g. "bardok-w3sp[14736][2963].dm_68"  → map=bardok-w3sp, time=14736ms, mddId=2963
    // Older pattern used underscores: tatmt-s5_14736_2963.dm_68
    private function parseDemoFilename(string $name): array
    {
        $base = preg_replace('/\.dm_68$/i', '', $name);

        if (preg_match('/^(.+?)\[(\d+)\]\[(\d+)\]$/', $base, $m)) {
            return ['map' => $m[1], 'time' => (int) $m[2], 'player' => (int) $m[3]];
        }
        if (preg_match('/^(.+?)_(\d+)_(\d+)$/', $base, $m)) {
            return ['map' => $m[1], 'time' => (int) $m[2], 'player' => (int) $m[3]];
        }
        return ['map' => $base, 'time' => null, 'player' => null];
    }

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

    public function refreshStats(): void
    {
        foreach (SftpCredential::where('status', 'active')->pluck('sftp_username') as $u) {
            Cache::forget("serverdemos:stats:{$u}");
            Cache::forget("serverdemos:list:{$u}");
        }
        Notification::make()->title('Stats refreshed')->success()->send();
    }

    // Livewire can't reliably carry a streamed download itself (a hand-built
    // StreamedResponse isn't recognised - the button silently did nothing on
    // prod), so hand the browser a short-lived signed url instead. The
    // controller re-checks auth + this panel's permission and streams the
    // file SFTP -> HTTP with no local temp file anywhere.
    public function downloadFile(string $relPath)
    {
        if ($this->selectedUser === '') return null;

        // Hard-confine the relPath to the selected user's subtree.
        $relPath = ltrim($relPath, '/');
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
