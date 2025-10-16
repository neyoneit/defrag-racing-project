<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlayerModel;
use Illuminate\Support\Facades\DB;

class ImportBaseWeapons extends Command
{
    protected $signature = 'models:import-weapons';
    protected $description = 'Import base Q3 weapon models from public/baseq3/models/weapons2/ into database';

    public function handle()
    {
        $this->info('Importing base Q3 weapon models...');

        $baseq3Path = public_path('baseq3/models/weapons2');

        if (!is_dir($baseq3Path)) {
            $this->error('baseq3/models/weapons2 directory not found');
            return 1;
        }

        // Get all weapon directories
        $weapons = array_diff(scandir($baseq3Path), ['.', '..']);

        if (empty($weapons)) {
            $this->error('No weapon models found in baseq3/models/weapons2 directory');
            return 1;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($weapons as $weaponName) {
            $weaponPath = $baseq3Path . '/' . $weaponName;

            if (!is_dir($weaponPath)) {
                continue;
            }

            // Check if weapon already exists
            $existing = PlayerModel::where('name', $weaponName)
                ->where('category', 'weapon')
                ->where('author', 'id Software, Inc.')
                ->first();

            if ($existing) {
                $this->warn("Skipped: {$weaponName} (already exists)");
                $skipped++;
                continue;
            }

            // Check for MD3 files to confirm it's a weapon model
            $md3Files = glob($weaponPath . '/*.md3');

            if (empty($md3Files)) {
                $this->warn("Skipped: {$weaponName} (no MD3 files found)");
                $skipped++;
                continue;
            }

            // Find the main weapon MD3 file (usually named weaponname.md3)
            $mainMd3 = null;
            foreach ($md3Files as $md3File) {
                $basename = basename($md3File, '.md3');
                // Look for the base file without suffixes like _1, _2, _hand, _flash, _barrel
                if ($basename === $weaponName) {
                    $mainMd3 = basename($md3File);
                    break;
                }
            }

            // If no exact match, use the first MD3 file that's not a variant
            if (!$mainMd3) {
                foreach ($md3Files as $md3File) {
                    $basename = basename($md3File, '.md3');
                    if (!preg_match('/_(hand|flash|barrel|[0-9])$/', $basename)) {
                        $mainMd3 = basename($md3File);
                        break;
                    }
                }
            }

            // Last resort: use first MD3 file
            if (!$mainMd3) {
                $mainMd3 = basename($md3Files[0]);
            }

            // Get available skins (if any)
            $skinFiles = glob($weaponPath . '/*.skin');
            $availableSkins = [];

            foreach ($skinFiles as $skinFile) {
                $skinBasename = basename($skinFile, '.skin');

                // Extract skin name (e.g., weapon_red.skin -> red)
                if (str_starts_with($skinBasename, $weaponName . '_')) {
                    $skinName = str_replace($weaponName . '_', '', $skinBasename);
                } else {
                    $skinName = $skinBasename;
                }

                if (!in_array($skinName, $availableSkins)) {
                    $availableSkins[] = $skinName;
                }
            }

            // Ensure 'default' is always first if it exists
            if (in_array('default', $availableSkins)) {
                $availableSkins = array_diff($availableSkins, ['default']);
                array_unshift($availableSkins, 'default');
            }

            // Create weapon model record
            PlayerModel::create([
                'user_id' => 8, // neyoneit (admin)
                'name' => $weaponName,
                'base_model' => $weaponName,
                'model_type' => 'complete',
                'description' => "Base Quake III Arena weapon model",
                'category' => 'weapon',
                'author' => 'id Software, Inc.',
                'author_email' => null,
                'file_path' => 'baseq3/models/weapons2/' . $weaponName,
                'main_file' => $mainMd3,
                'zip_path' => null,
                'thumbnail' => null,
                'downloads' => 0,
                'poly_count' => null,
                'vert_count' => null,
                'has_sounds' => false,
                'has_ctf_skins' => false,
                'available_skins' => json_encode($availableSkins ?: ['default']),
                'approved' => true,
            ]);

            $this->info("✓ Imported: {$weaponName} (main file: {$mainMd3})");
            $imported++;
        }

        $this->info('');
        $this->info("Import complete!");
        $this->info("Imported: {$imported} weapons");
        $this->info("Skipped: {$skipped} weapons");

        return 0;
    }
}
