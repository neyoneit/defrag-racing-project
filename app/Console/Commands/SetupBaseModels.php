<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class SetupBaseModels extends Command
{
    protected $signature = 'setup:base-q3';
    protected $description = 'Extract base Q3 game data from pak0.pk3 (models, sounds, textures, etc.)';

    public function handle()
    {
        $this->info('Setting up base Q3 game data from pak files...');
        $this->info('pak0.pk3 contains base game data: models, sounds, textures, gfx, scripts, etc.');
        $this->info('pak2.pk3 contains additional Team Arena content');

        $extractPath = public_path('baseq3');
        @mkdir($extractPath, 0755, true);

        // Extract pak0.pk3 first
        $pak0Path = storage_path('app/pak0.pk3');
        if (!file_exists($pak0Path)) {
            $this->error('pak0.pk3 not found at: ' . $pak0Path);
            return 1;
        }

        $this->info('Extracting pak0.pk3...');
        $zip = new ZipArchive();
        if ($zip->open($pak0Path) !== true) {
            $this->error('Failed to open pak0.pk3');
            return 1;
        }

        $pak0Files = $zip->numFiles;
        $zip->extractTo($extractPath);
        $zip->close();
        $this->info("✓ Extracted {$pak0Files} files from pak0.pk3");

        // Extract pak2.pk3 (overwrites/adds to pak0)
        $pak2Path = storage_path('app/pak2.pk3');
        if (file_exists($pak2Path)) {
            $this->info('Extracting pak2.pk3...');
            $zip = new ZipArchive();
            if ($zip->open($pak2Path) === true) {
                $pak2Files = $zip->numFiles;
                $zip->extractTo($extractPath);
                $zip->close();
                $this->info("✓ Extracted {$pak2Files} files from pak2.pk3");
            } else {
                $this->warn('Failed to open pak2.pk3, skipping...');
            }
        } else {
            $this->warn('pak2.pk3 not found, skipping Team Arena content');
        }

        $this->info('Base Q3 data installed to: ' . $extractPath);

        // List the installed base models
        $playersPath = $extractPath . '/models/players';
        if (is_dir($playersPath)) {
            $models = array_diff(scandir($playersPath), ['.', '..']);
            $this->info('Available base player models (' . count($models) . '): ' . implode(', ', $models));
        }

        $this->info('✓ Setup complete!');
        $this->info('Files are now accessible at: /baseq3/*');

        return 0;
    }
}
