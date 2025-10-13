<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlayerModel;
use Illuminate\Support\Facades\DB;

class ImportBaseModels extends Command
{
    protected $signature = 'models:import-base';
    protected $description = 'Import base Q3 player models from public/baseq3/ into database';

    public function handle()
    {
        $this->info('Importing base Q3 player models...');

        $baseq3Path = public_path('baseq3/models/players');

        if (!is_dir($baseq3Path)) {
            $this->error('baseq3 directory not found. Please run: php artisan setup:base-q3');
            return 1;
        }

        // Get all model directories
        $models = array_diff(scandir($baseq3Path), ['.', '..']);

        if (empty($models)) {
            $this->error('No models found in baseq3 directory');
            return 1;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($models as $modelName) {
            $modelPath = $baseq3Path . '/' . $modelName;

            if (!is_dir($modelPath)) {
                continue;
            }

            // Check if model already exists
            $existing = PlayerModel::where('name', $modelName)
                ->where('author', 'id Software, Inc.')
                ->first();

            if ($existing) {
                $this->warn("Skipped: {$modelName} (already exists)");
                $skipped++;
                continue;
            }

            // Check for MD3 files to confirm it's a complete model
            $hasHead = file_exists($modelPath . '/head.md3');
            $hasUpper = file_exists($modelPath . '/upper.md3');
            $hasLower = file_exists($modelPath . '/lower.md3');

            if (!$hasHead || !$hasUpper || !$hasLower) {
                $this->warn("Skipped: {$modelName} (missing MD3 files)");
                $skipped++;
                continue;
            }

            // Get available skins
            // Skin files come in sets of 3: head_X.skin, lower_X.skin, upper_X.skin
            // Or in format: modelname_X.skin
            $skinFiles = glob($modelPath . '/*.skin');
            $availableSkins = [];

            foreach ($skinFiles as $skinFile) {
                $skinBasename = basename($skinFile, '.skin');

                // Try pattern 1: head_X.skin, lower_X.skin, upper_X.skin
                if (preg_match('/^(head|lower|upper)_(.+)$/', $skinBasename, $matches)) {
                    $skinName = $matches[2]; // Extract X from head_X
                }
                // Try pattern 2: modelname_X.skin
                else if (str_starts_with($skinBasename, $modelName . '_')) {
                    $skinName = str_replace($modelName . '_', '', $skinBasename);
                }
                // Fallback: use full basename
                else {
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

            // Create model record
            PlayerModel::create([
                'user_id' => 1, // Admin user (adjust if needed)
                'name' => $modelName,
                'base_model' => $modelName, // Base models reference themselves
                'model_type' => 'complete',
                'description' => "Base Quake III Arena character model",
                'category' => 'player',
                'author' => 'id Software, Inc.',
                'author_email' => null,
                'file_path' => 'baseq3/models/players/' . $modelName,
                'zip_path' => null, // No PK3 for base models
                'thumbnail' => null,
                'downloads' => 0,
                'poly_count' => null,
                'vert_count' => null,
                'has_sounds' => true, // Base models have sounds
                'has_ctf_skins' => in_array('red', $availableSkins) || in_array('blue', $availableSkins),
                'available_skins' => json_encode($availableSkins),
                'approved' => true, // Auto-approve base models
            ]);

            $this->info("✓ Imported: {$modelName}");
            $imported++;
        }

        $this->info('');
        $this->info("Import complete!");
        $this->info("Imported: {$imported} models");
        $this->info("Skipped: {$skipped} models");

        return 0;
    }
}
