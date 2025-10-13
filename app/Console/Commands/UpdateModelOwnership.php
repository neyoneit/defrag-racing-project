<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlayerModel;
use Illuminate\Support\Facades\DB;

class UpdateModelOwnership extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'models:update-ownership {oldUserId} {newUserId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transfer ownership of models from one user to another';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $oldUserId = (int) $this->argument('oldUserId');
        $newUserId = (int) $this->argument('newUserId');

        $this->info("Transferring model ownership from user {$oldUserId} to user {$newUserId}");

        // Get models with old user ID
        $models = PlayerModel::where('user_id', $oldUserId)->get();

        if ($models->isEmpty()) {
            $this->warn("No models found with user_id = {$oldUserId}");
            return 0;
        }

        $this->info("Found {$models->count()} models to transfer:");
        $this->table(
            ['ID', 'Name', 'Category', 'Created'],
            $models->map(fn($m) => [$m->id, $m->name, $m->category, $m->created_at->format('Y-m-d H:i')])->toArray()
        );

        if (!$this->confirm('Do you want to proceed with the transfer?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        DB::beginTransaction();

        try {
            $updated = PlayerModel::where('user_id', $oldUserId)
                ->update(['user_id' => $newUserId]);

            DB::commit();

            $this->info('');
            $this->info("✅ Transfer complete! Updated {$updated} models");
            $this->info("All models now belong to user {$newUserId}");

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error occurred during transfer: ' . $e->getMessage());
            $this->error('All changes have been rolled back.');
            return 1;
        }
    }
}
