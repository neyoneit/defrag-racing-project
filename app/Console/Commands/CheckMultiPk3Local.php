<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CheckMultiPk3Local extends Command
{
    protected $signature = 'models:check-multi-pk3';

    protected $description = 'Scan stored PK3 files to find which ones are actually ZIPs containing multiple PK3s inside';

    public function handle()
    {
        $pk3Dir = storage_path('app/models/pk3s');

        if (!is_dir($pk3Dir)) {
            $this->error("Directory not found: {$pk3Dir}");
            return 1;
        }

        $files = array_diff(scandir($pk3Dir), ['.', '..']);
        $total = count($files);
        $this->info("Scanning {$total} files in {$pk3Dir}...");
        $this->newLine();

        $multiPk3 = [];
        $notZip = [];
        $singlePk3 = 0;
        $noPk3 = 0;
        $checked = 0;

        foreach ($files as $file) {
            $fullPath = $pk3Dir . '/' . $file;
            if (!is_file($fullPath)) continue;

            $checked++;

            $zip = new ZipArchive();
            $result = $zip->open($fullPath);

            if ($result !== true) {
                $notZip[] = $file;
                continue;
            }

            $pk3sInside = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('/\.pk3$/i', $name) && !str_contains($name, '/')) {
                    $pk3sInside[] = $name;
                }
            }

            $zip->close();

            if (count($pk3sInside) > 1) {
                $multiPk3[] = [
                    'file' => $file,
                    'pk3s' => $pk3sInside,
                    'count' => count($pk3sInside),
                ];
                $this->warn("[MULTI] {$file} contains " . count($pk3sInside) . " PK3s: " . implode(', ', $pk3sInside));
            } elseif (count($pk3sInside) === 1) {
                // This is a ZIP containing a single PK3 - unusual but not multi
                $singlePk3++;
            } else {
                // It's a valid ZIP but contains no PK3 files at root level (normal PK3 file)
                $noPk3++;
            }
        }

        $this->newLine();
        $this->info("╔══════════════════════════════════════════╗");
        $this->info("║         MULTI-PK3 CHECK SUMMARY         ║");
        $this->info("╠══════════════════════════════════════════╣");
        $this->info("║  Files scanned:       {$checked}");
        $this->info("║  Normal PK3 files:    {$noPk3}");
        $this->info("║  ZIP with 1 PK3:      {$singlePk3}");
        $this->warn("║  ZIP with multi PK3:  " . count($multiPk3));

        if (!empty($notZip)) {
            $this->error("║  Not a valid ZIP:     " . count($notZip));
        }

        $this->info("╚══════════════════════════════════════════╝");

        if (!empty($multiPk3)) {
            $this->newLine();
            $this->info("=== MULTI-PK3 FILES ===");
            foreach ($multiPk3 as $m) {
                $this->warn("{$m['file']} ({$m['count']} PK3s):");
                foreach ($m['pk3s'] as $pk3) {
                    $this->line("  - {$pk3}");
                }
            }
        }

        // Save report
        $report = [
            'generated_at' => now()->toIso8601String(),
            'stats' => [
                'total_scanned' => $checked,
                'normal_pk3' => $noPk3,
                'zip_single_pk3' => $singlePk3,
                'zip_multi_pk3' => count($multiPk3),
                'not_valid_zip' => count($notZip),
            ],
            'multi_pk3_files' => $multiPk3,
            'not_valid_zip_files' => $notZip,
        ];

        $reportPath = 'multi-pk3-check-' . date('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info("Report saved to: storage/app/{$reportPath}");

        return 0;
    }
}
