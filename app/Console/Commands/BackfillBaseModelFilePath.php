<?php

namespace App\Console\Commands;

use App\Models\PlayerModel;
use Illuminate\Console\Command;

class BackfillBaseModelFilePath extends Command
{
    protected $signature = 'models:backfill-base-path';
    protected $description = 'Backfill base_model_file_path for skin/mixed packs that are missing it';

    public function handle()
    {
        $models = PlayerModel::whereNotNull('base_model')
            ->where('model_type', '!=', 'complete')
            ->where(function ($q) {
                $q->whereNull('base_model_file_path')->orWhere('base_model_file_path', '');
            })
            ->get(['id', 'name', 'base_model', 'model_type']);

        $this->info("Found {$models->count()} models missing base_model_file_path");

        // List of base Q3 models (in baseq3/models/players/)
        $baseQ3Models = [
            'sarge', 'grunt', 'major', 'visor', 'slash', 'biker', 'tankjr',
            'orbb', 'crash', 'razor', 'doom', 'klesk', 'anarki', 'xaero',
            'mynx', 'hunter', 'bones', 'sorlag', 'lucy', 'keel', 'uriel',
            'ranger', 'bitterman', 'brandon', 'carmack', 'cash', 'light',
            'medium', 'paulj', 'tim', 'xian',
        ];

        $fixed = 0;
        $skipped = 0;

        foreach ($models as $model) {
            $baseName = strtolower($model->base_model);

            if (in_array($baseName, $baseQ3Models)) {
                // Base Q3 model
                $filePath = "baseq3/models/players/{$baseName}";
                $model->base_model_file_path = $filePath;
                $model->save();
                $this->line("  ✓ #{$model->id} {$model->name} → {$filePath} (baseq3)");
                $fixed++;
            } else {
                // Custom model - find a complete model with same base_model
                $baseModel = PlayerModel::whereRaw('LOWER(base_model) = ?', [$baseName])
                    ->where('model_type', 'complete')
                    ->whereNotNull('file_path')
                    ->first(['file_path']);

                if ($baseModel) {
                    $model->base_model_file_path = $baseModel->file_path;
                    $model->save();
                    $this->line("  ✓ #{$model->id} {$model->name} → {$baseModel->file_path}");
                    $fixed++;
                } else {
                    $this->warn("  ✗ #{$model->id} {$model->name} — no complete '{$model->base_model}' model found");
                    $skipped++;
                }
            }
        }

        $this->info("Done: {$fixed} fixed, {$skipped} skipped");
    }
}
