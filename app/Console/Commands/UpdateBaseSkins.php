<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlayerModel;

class UpdateBaseSkins extends Command
{
    protected $signature = 'models:update-base-skins';
    protected $description = 'Update available_skins for base Q3 models with corrected skin names';

    public function handle()
    {
        $this->info('Updating skin names for base Q3 player models...');

        $baseModels = PlayerModel::where('author', 'id Software, Inc.')->get();

        if ($baseModels->isEmpty()) {
            $this->error('No base models found');
            return 1;
        }

        $updated = 0;

        foreach ($baseModels as $model) {
            $modelPath = public_path($model->file_path);

            if (!is_dir($modelPath)) {
                $this->warn("Skipped: {$model->name} (directory not found)");
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
                else if (str_starts_with($skinBasename, $model->name . '_')) {
                    $skinName = str_replace($model->name . '_', '', $skinBasename);
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

            // Update model
            $model->available_skins = json_encode($availableSkins);
            $model->has_ctf_skins = in_array('red', $availableSkins) || in_array('blue', $availableSkins);
            $model->save();

            $this->info("✓ Updated: {$model->name} - Skins: " . implode(', ', $availableSkins));
            $updated++;
        }

        $this->info('');
        $this->info("Update complete! Updated {$updated} models");

        return 0;
    }
}
