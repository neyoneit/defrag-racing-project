<?php

namespace App\Filament\Pages;

use App\Models\SftpCredential;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        return Cache::remember("serverdemos:list:{$user}", 60, function () use ($user) {
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

    // Sidebar: every active SFTP credential + live stats, derived from the
    // same cached listing the file pane uses (no separate traversal).
    public function getCredentialsProperty(): array
    {
        $creds = SftpCredential::with('user:id,name,plain_name,mdd_id')
            ->where('status', 'active')
            ->get();

        return $creds->map(function ($c) {
            $user = $c->sftp_username;
            $files = $this->listing($user);
            $last = null;
            $bytes = 0;
            foreach ($files as $f) {
                $bytes += (int) ($f['size'] ?? 0);
                if ($f['mtime'] && (!$last || $f['mtime'] > $last)) $last = $f['mtime'];
            }

            return [
                'sftp_username' => $user,
                'owner_name'    => $c->user?->plain_name ?: ($c->user?->name ?: '?'),
                'owner_id'      => $c->user_id,
                'count'         => count($files),
                'last'          => $last,
                'bytes'         => $bytes,
            ];
        })->sortByDesc('last')->values()->all();
    }

    // Main pane: the current page of files for the selected user.
    public function getFilesProperty(): array
    {
        return array_slice(
            $this->filteredRows(),
            ($this->page - 1) * self::PER_PAGE,
            self::PER_PAGE
        );
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
        if (!in_array($col, ['name', 'size', 'mtime', 'map', 'time'], true)) return;
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

    public function downloadFile(string $relPath)
    {
        if ($this->selectedUser === '') return null;

        // Hard-confine the relPath to the selected user's subtree.
        $relPath = ltrim($relPath, '/');
        if (!str_starts_with($relPath, $this->selectedUser . '/')) {
            Notification::make()->title('Forbidden')->danger()->send();
            return null;
        }

        $disk = $this->disk();
        if (!$disk->exists($relPath)) {
            Notification::make()->title('File not found')->danger()->send();
            return null;
        }

        $size = $disk->size($relPath);
        $basename = basename($relPath);

        return new StreamedResponse(function () use ($disk, $relPath) {
            $stream = $disk->readStream($relPath);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes($basename) . '"',
            'Content-Length'      => (string) $size,
            'X-Accel-Buffering'   => 'no',
        ]);
    }
}
