<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CalculateRatingsRust extends Command
{
    protected $signature = 'ratings:calculate {--physics= : vq3 or cpm} {--mode=run : game mode} {--category= : ranking category}';
    protected $description = 'Calculate ratings using Rust (100x faster than SQL)';

    // All available categories
    const CATEGORIES = ['overall', 'rocket', 'plasma', 'grenade', 'slick', 'tele', 'bfg', 'strafe'];

    public function handle()
    {
        $physics = $this->option('physics');
        $mode = $this->option('mode') ?? 'run';
        $category = $this->option('category');

        if (!$physics) {
            // Run for both VQ3 and CPM, all categories
            $this->info('Calculating ratings for VQ3 and CPM, all categories...');

            $this->call('ratings:calculate', ['--physics' => 'vq3', '--mode' => $mode]);
            $this->call('ratings:calculate', ['--physics' => 'cpm', '--mode' => $mode]);

            return 0;
        }

        if (!$category) {
            // Run all categories for this physics
            $this->info("Calculating all categories for {$physics} {$mode}...");
            foreach (self::CATEGORIES as $cat) {
                $this->call('ratings:calculate', ['--physics' => $physics, '--mode' => $mode, '--category' => $cat]);
            }
            return 0;
        }

        $this->info("Calculating {$physics} {$mode} [{$category}] ratings using Rust...");
        $start = microtime(true);

        // Path to Rust binary (production: storage/app, docker: /tmp)
        $rustBinary = storage_path('app/defrag_rating');
        if (!file_exists($rustBinary)) {
            $rustBinary = '/tmp/defrag_rating'; // Fallback for Docker
        }

        if (!file_exists($rustBinary)) {
            $this->error("Rust binary not found! Run: ./build-rust.sh");
            return 1;
        }

        // Execute Rust binary with category
        $output = [];
        $returnCode = 0;
        exec("$rustBinary $physics $mode $category 2>&1", $output, $returnCode);

        $duration = round(microtime(true) - $start, 2);

        if ($returnCode === 0) {
            $this->info("✓ Completed in {$duration}s");
            foreach ($output as $line) {
                $this->line($line);
            }

            Log::info("Rust ratings calculated", [
                'physics' => $physics,
                'mode' => $mode,
                'category' => $category,
                'duration' => $duration
            ]);

            return 0;
        } else {
            $this->error("Failed to calculate ratings");
            foreach ($output as $line) {
                $this->error($line);
            }

            Log::error("Rust ratings calculation failed", [
                'physics' => $physics,
                'mode' => $mode,
                'category' => $category,
                'output' => implode("\n", $output)
            ]);

            return 1;
        }
    }
}
