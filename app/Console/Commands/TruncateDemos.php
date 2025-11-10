<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UploadedDemo;
use App\Models\OfflineRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TruncateDemos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demos:truncate {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all uploaded demos and offline records (WARNING: This is irreversible!)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            $this->warn('⚠️  WARNING: This will DELETE ALL uploaded demos and offline records!');
            $this->warn('This includes:');
            $this->warn('  - All uploaded_demos records');
            $this->warn('  - All offline_records');
            $this->warn('  - Demo files from Backblaze B2 storage (if configured)');
            $this->newLine();

            if (!$this->confirm('Are you absolutely sure you want to continue?', false)) {
                $this->info('Cancelled.');
                return 0;
            }

            if (!$this->confirm('This action cannot be undone. Continue?', false)) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        $this->info('Starting demo truncation...');

        // Get counts before deletion
        $demoCount = UploadedDemo::count();
        $offlineRecordCount = OfflineRecord::count();

        $this->info("Found {$demoCount} uploaded demos and {$offlineRecordCount} offline records");

        // Delete demo files from storage (optional - comment out if you want to keep files)
        if (config('filesystems.default') === 'b2') {
            $this->info('Deleting demo files from Backblaze B2...');
            $files = Storage::allFiles('demos');
            $fileCount = count($files);

            if ($fileCount > 0) {
                $bar = $this->output->createProgressBar($fileCount);
                $bar->start();

                foreach ($files as $file) {
                    Storage::delete($file);
                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
                $this->info("✓ Deleted {$fileCount} demo files from storage");
            } else {
                $this->info('No demo files found in storage');
            }
        }

        // Truncate tables
        $this->info('Truncating database tables...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        OfflineRecord::truncate();
        $this->info('✓ Truncated offline_records table');

        UploadedDemo::truncate();
        $this->info('✓ Truncated uploaded_demos table');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('✅ All demos and offline records have been deleted!');
        $this->info("Deleted {$demoCount} uploaded demos and {$offlineRecordCount} offline records");

        // Restart queue workers to load latest code changes
        $this->newLine();
        $this->info('Restarting queue workers to load latest code...');

        // First signal any existing workers to stop
        $this->call('queue:restart');

        // Wait a moment for workers to shut down
        sleep(2);

        // Start fresh workers (8 workers by default)
        $workers = 8;
        $this->info("Starting {$workers} fresh queue workers...");

        for ($i = 1; $i <= $workers; $i++) {
            exec('docker exec -d defrag-racing-project-laravel.test-1 php artisan queue:work redis --queue=demos --tries=3 --timeout=300 --sleep=3');
        }

        $this->info("✓ Started {$workers} queue workers with latest code");

        return 0;
    }
}
