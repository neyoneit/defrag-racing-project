<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Map;

class CleanOrphanThumbs extends Command
{
    protected $signature = 'maps:clean-orphan-thumbs';
    protected $description = 'Delete thumbnail files not referenced by any map';

    public function handle()
    {
        $dbThumbs = Map::whereNotNull('thumbnail')
            ->where('thumbnail', '!=', '')
            ->pluck('thumbnail')
            ->map(fn($t) => basename($t))
            ->flip()
            ->toArray();

        $dir = storage_path('app/public/thumbs/');
        $files = scandir($dir);
        $deleted = 0;
        $freed = 0;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir($dir . $file)) continue;
            if (!isset($dbThumbs[$file])) {
                $path = $dir . $file;
                $freed += filesize($path);
                unlink($path);
                $deleted++;
            }
        }

        $this->info("Deleted: {$deleted} orphans");
        $this->info("Freed: " . round($freed / 1024 / 1024, 1) . " MB");
        $this->info("Remaining: " . (count(scandir($dir)) - 2) . " files");
    }
}
