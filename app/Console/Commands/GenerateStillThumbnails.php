<?php

namespace App\Console\Commands;

use App\Models\PlayerModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateStillThumbnails extends Command
{
    protected $signature = 'models:generate-thumbnails
        {--limit=0 : Limit number of models to process (0 = all)}
        {--force : Overwrite existing thumbnails}
        {--ids= : Comma-separated model IDs to process}';

    protected $description = 'Generate still PNG thumbnails from idle GIFs by extracting the middle frame';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $ids = $this->option('ids');

        $query = PlayerModel::whereNotNull('idle_gif');

        if (!$force) {
            $query->whereNull('thumbnail');
        }

        if ($ids) {
            $idList = array_map('intval', explode(',', $ids));
            $query->whereIn('id', $idList);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $models = $query->select('id', 'name', 'idle_gif', 'thumbnail')->get();

        if ($models->isEmpty()) {
            $this->info('No models need thumbnail generation.');
            return 0;
        }

        $this->info("Processing {$models->count()} models...");

        $thumbnailsDir = storage_path('app/public/thumbnails');
        if (!file_exists($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0755, true);
        }

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($models as $model) {
            $gifPath = storage_path('app/public/' . $model->idle_gif);

            if (!file_exists($gifPath)) {
                $this->warn("[#{$model->id}] {$model->name} - idle GIF not found: {$model->idle_gif}");
                $failed++;
                continue;
            }

            try {
                $pngBlob = $this->extractMiddleFrame($gifPath);

                if (!$pngBlob) {
                    $this->warn("[#{$model->id}] {$model->name} - failed to extract frame");
                    $failed++;
                    continue;
                }

                $filename = "model_{$model->id}.png";
                $outputPath = $thumbnailsDir . '/' . $filename;
                file_put_contents($outputPath, $pngBlob);

                $model->update(['thumbnail' => "thumbnails/{$filename}"]);

                $success++;
                $this->line("[#{$model->id}] {$model->name} - OK");
            } catch (\Exception $e) {
                $this->error("[#{$model->id}] {$model->name} - {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done: {$success} generated, {$failed} failed");

        return 0;
    }

    private function extractMiddleFrame(string $gifPath): ?string
    {
        $gif = @imagecreatefromgif($gifPath);
        if (!$gif) {
            return null;
        }

        // GD can only read the first frame of a GIF natively.
        // Use Imagick if available for middle frame extraction.
        if (extension_loaded('imagick')) {
            return $this->extractWithImagick($gifPath);
        }

        // Fallback: use first frame via GD
        return $this->convertGdToPng($gif);
    }

    private function extractWithImagick(string $gifPath): ?string
    {
        $imagick = new \Imagick($gifPath);
        $frameCount = $imagick->getNumberImages();

        // Get middle frame
        $middleIndex = (int) floor($frameCount / 2);
        $imagick->setIteratorIndex($middleIndex);

        // Coalesce to get proper frame (handles disposal methods)
        $coalesced = $imagick->coalesceImages();
        for ($i = 0; $i < $middleIndex; $i++) {
            $coalesced->nextImage();
        }

        $coalesced->setImageFormat('png');
        $pngData = $coalesced->getImageBlob();

        $coalesced->clear();
        $imagick->clear();

        return $pngData;
    }

    private function convertGdToPng($image): ?string
    {
        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        imagedestroy($image);
        return $data ?: null;
    }
}
