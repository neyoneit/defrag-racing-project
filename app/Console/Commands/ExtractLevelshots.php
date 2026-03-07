<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Map;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExtractLevelshots extends Command
{
    protected $signature = 'maps:extract-levelshots {name? : Process specific map by name} {--limit=50 : Max maps to process} {--force : Reprocess maps that already have thumbnails} {--fix-placeholders : Replace 11KB WorldSpawn placeholder thumbnails}';
    protected $description = 'Extract levelshots from pk3 files for maps missing thumbnails';

    private const PLACEHOLDER_SIZE = 11419;

    public function handle()
    {
        if ($this->argument('name')) {
            $maps = Map::where('name', $this->argument('name'))->whereNotNull('pk3')->get();
            if ($maps->isEmpty()) {
                $this->error("Map '{$this->argument('name')}' not found or has no pk3");
                return;
            }
        } elseif ($this->option('fix-placeholders')) {
            $all = Map::whereNotNull('pk3')->whereNotNull('thumbnail')->where('thumbnail', '!=', '')->get();
            $maps = $all->filter(function ($map) {
                $path = storage_path('app/public/' . $map->thumbnail);
                return file_exists($path) && filesize($path) === self::PLACEHOLDER_SIZE;
            })->values();
        } else {
            $query = Map::query()->whereNotNull('pk3');

            if (!$this->option('force')) {
                $query->where(function ($q) {
                    $q->whereNull('thumbnail')->orWhere('thumbnail', '');
                });
            }

            $maps = $query->limit($this->option('limit'))->get();
        }

        $this->info("Found {$maps->count()} maps to process");

        $processed = 0;
        $extracted = 0;

        foreach ($maps as $map) {
            $processed++;
            $this->info("[{$processed}/{$maps->count()}] Processing: {$map->name}");

            $pk3Url = "https://dl.defrag.racing/downloads/maps/" . $map->pk3;

            // Check file exists and get size
            $ch = curl_init($pk3Url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->warn("  Pk3 not found: {$pk3Url} (HTTP {$httpCode})");
                continue;
            }

            // Skip files larger than 200MB
            if ($fileSize > 200 * 1024 * 1024) {
                $this->warn("  Pk3 too large: " . round($fileSize / 1024 / 1024, 1) . "MB, skipping");
                continue;
            }

            // Download pk3 to temp file
            $tempFile = tempnam(sys_get_temp_dir(), 'pk3_');

            $ch = curl_init($pk3Url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            $fp = fopen($tempFile, 'w');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            $downloadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($downloadCode !== 200) {
                $this->warn("  Download failed (HTTP {$downloadCode})");
                @unlink($tempFile);
                continue;
            }

            // Open pk3 (zip)
            $zip = new \ZipArchive();
            if ($zip->open($tempFile) !== true) {
                $this->warn("  Failed to open pk3 as zip");
                @unlink($tempFile);
                continue;
            }

            // Search for levelshot
            $levelshotData = null;
            $levelshotExt = null;
            $mapBaseName = strtolower($map->name);

            $searchPaths = [
                "levelshots/{$mapBaseName}.jpg",
                "levelshots/{$mapBaseName}.jpeg",
                "levelshots/{$mapBaseName}.png",
                "levelshots/{$mapBaseName}.tga",
            ];

            // Also try case-insensitive search through all entries
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                $entryLower = strtolower($entryName);

                foreach ($searchPaths as $searchPath) {
                    if ($entryLower === $searchPath) {
                        $levelshotData = $zip->getFromIndex($i);
                        $levelshotExt = pathinfo($entryName, PATHINFO_EXTENSION);
                        break 2;
                    }
                }
            }

            $zip->close();
            @unlink($tempFile);

            if ($levelshotData === null) {
                $this->warn("  No levelshot found in pk3");
                continue;
            }

            // Convert TGA to JPG if needed
            if (strtolower($levelshotExt) === 'tga') {
                $image = $this->loadTga($levelshotData);
                if ($image === null) {
                    $this->warn("  Failed to convert TGA levelshot");
                    continue;
                }

                ob_start();
                imagejpeg($image, null, 90);
                $levelshotData = ob_get_clean();
                imagedestroy($image);
                $levelshotExt = 'jpg';
            }

            // Save as thumbnail
            $filename = "thumbs/" . Str::random(20) . '.' . $levelshotExt;
            Storage::disk('public')->put($filename, $levelshotData);

            $map->thumbnail = $filename;
            $map->save();

            $extracted++;
            $this->info("  Extracted levelshot -> {$filename}");
        }

        $this->info("Done. Processed: {$processed}, Extracted: {$extracted}");
    }

    private function loadTga($data)
    {
        if (strlen($data) < 18) {
            return null;
        }

        $header = unpack('CidLength/CcolorMapType/CimageType/vcolorMapStart/vcolorMapLength/CcolorMapDepth/vxOrigin/vyOrigin/vwidth/vheight/CbitsPerPixel/CimageDescriptor', $data);

        if (!in_array($header['imageType'], [2, 10])) {
            return null;
        }

        $width = $header['width'];
        $height = $header['height'];
        $bpp = $header['bitsPerPixel'];

        if ($width <= 0 || $height <= 0 || !in_array($bpp, [24, 32])) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);
        $offset = 18 + $header['idLength'];

        $flipVertical = !($header['imageDescriptor'] & 0x20);

        if ($header['imageType'] === 2) {
            // Uncompressed
            $bytesPerPixel = $bpp / 8;
            for ($y = 0; $y < $height; $y++) {
                $row = $flipVertical ? ($height - 1 - $y) : $y;
                for ($x = 0; $x < $width; $x++) {
                    $pos = $offset + ($y * $width + $x) * $bytesPerPixel;
                    $b = ord($data[$pos]);
                    $g = ord($data[$pos + 1]);
                    $r = ord($data[$pos + 2]);
                    imagesetpixel($image, $x, $row, imagecolorallocate($image, $r, $g, $b));
                }
            }
        } else {
            // RLE compressed
            $pixelCount = $width * $height;
            $bytesPerPixel = $bpp / 8;
            $currentPixel = 0;
            $pos = $offset;

            while ($currentPixel < $pixelCount && $pos < strlen($data)) {
                $packetHeader = ord($data[$pos++]);
                $packetLength = ($packetHeader & 0x7F) + 1;

                if ($packetHeader & 0x80) {
                    // RLE packet
                    $b = ord($data[$pos]);
                    $g = ord($data[$pos + 1]);
                    $r = ord($data[$pos + 2]);
                    $pos += $bytesPerPixel;
                    $color = imagecolorallocate($image, $r, $g, $b);

                    for ($i = 0; $i < $packetLength && $currentPixel < $pixelCount; $i++) {
                        $x = $currentPixel % $width;
                        $y = (int)($currentPixel / $width);
                        $row = $flipVertical ? ($height - 1 - $y) : $y;
                        imagesetpixel($image, $x, $row, $color);
                        $currentPixel++;
                    }
                } else {
                    // Raw packet
                    for ($i = 0; $i < $packetLength && $currentPixel < $pixelCount; $i++) {
                        $b = ord($data[$pos]);
                        $g = ord($data[$pos + 1]);
                        $r = ord($data[$pos + 2]);
                        $pos += $bytesPerPixel;

                        $x = $currentPixel % $width;
                        $y = (int)($currentPixel / $width);
                        $row = $flipVertical ? ($height - 1 - $y) : $y;
                        imagesetpixel($image, $x, $row, imagecolorallocate($image, $r, $g, $b));
                        $currentPixel++;
                    }
                }
            }
        }

        return $image;
    }
}
