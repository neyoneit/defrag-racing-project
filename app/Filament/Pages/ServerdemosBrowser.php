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

    // Sidebar: every active SFTP credential + live stats from the disk.
    // Stats are cached 60s to keep clicking responsive — the disk is SFTP,
    // a full traversal per render would be painful with hundreds of demos.
    public function getCredentialsProperty(): array
    {
        $creds = SftpCredential::with('user:id,name,plain_name,mdd_id')
            ->where('status', 'active')
            ->get();

        $disk = $this->disk();

        return $creds->map(function ($c) use ($disk) {
            $user = $c->sftp_username;
            $stats = Cache::remember("serverdemos:stats:{$user}", 60, function () use ($disk, $user) {
                try {
                    $files = $disk->allFiles($user);
                } catch (\Throwable) {
                    return ['count' => 0, 'last' => null, 'bytes' => 0];
                }

                $count = 0;
                $last = null;
                $bytes = 0;
                foreach ($files as $f) {
                    if (!str_ends_with(strtolower($f), '.dm_68')) continue;
                    $count++;
                    try {
                        $bytes += $disk->size($f) ?: 0;
                        $m = $disk->lastModified($f);
                        if ($m && (!$last || $m > $last)) $last = $m;
                    } catch (\Throwable) {
                    }
                }
                return ['count' => $count, 'last' => $last, 'bytes' => $bytes];
            });

            return [
                'sftp_username' => $user,
                'owner_name'    => $c->user?->plain_name ?: ($c->user?->name ?: '?'),
                'owner_id'      => $c->user_id,
                'count'         => $stats['count'],
                'last'          => $stats['last'],
                'bytes'         => $stats['bytes'],
            ];
        })->sortByDesc('last')->values()->all();
    }

    // Main pane: files for the selected user, filtered + sorted.
    public function getFilesProperty(): array
    {
        if ($this->selectedUser === '') return [];

        $disk = $this->disk();

        try {
            $paths = $disk->allFiles($this->selectedUser);
        } catch (\Throwable $e) {
            Notification::make()->title('Cannot list demos')->body($e->getMessage())->danger()->send();
            return [];
        }

        $rows = [];
        $needle = strtolower(trim($this->search));
        foreach ($paths as $p) {
            $name = basename($p);
            if (!str_ends_with(strtolower($name), '.dm_68')) continue;
            if ($needle !== '' && !str_contains(strtolower($name), $needle)) continue;

            $size = null;
            $mtime = null;
            try { $size = $disk->size($p); } catch (\Throwable) {}
            try { $mtime = $disk->lastModified($p); } catch (\Throwable) {}

            $parsed = $this->parseDemoFilename($name);

            $rows[] = [
                'path'  => $p,
                'name'  => $name,
                'size'  => $size,
                'mtime' => $mtime,
                'map'   => $parsed['map'],
                'time'  => $parsed['time'],
                'player'=> $parsed['player'],
            ];
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
    }

    public function goRoot(): void
    {
        $this->selectedUser = '';
        $this->search = '';
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
    }

    public function refreshStats(): void
    {
        foreach (SftpCredential::where('status', 'active')->pluck('sftp_username') as $u) {
            Cache::forget("serverdemos:stats:{$u}");
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
