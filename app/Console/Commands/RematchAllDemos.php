<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Services\NameMatcher;
use Illuminate\Console\Command;

class RematchAllDemos extends Command
{
    protected $signature = 'demos:rematch-all';
    protected $description = 'Rematch all unassigned demos against current user aliases';

    public function handle()
    {
        $this->info('Rematching all unassigned demos...');

        // Only rematch demos that are:
        // 1. Failed (status = 'failed')
        // 2. Not assigned (status != 'assigned') AND have low/no confidence
        // 3. Have player name available
        $demos = UploadedDemo::where(function($q) {
                // Either failed status OR (not assigned status AND low/no confidence)
                $q->where('status', 'failed')
                  ->orWhere(function($subQ) {
                      $subQ->where('status', '!=', 'assigned')
                           ->where(function($confQ) {
                               $confQ->where('name_confidence', '<', 100)
                                    ->orWhereNull('name_confidence');
                           });
                  });
            })
            ->whereNotNull('player_name')
            ->get();

        $this->info("Found {$demos->count()} demos to rematch");

        $progressBar = $this->output->createProgressBar($demos->count());
        $progressBar->start();

        $improved = 0;
        $nameMatcher = app(NameMatcher::class);

        foreach ($demos as $demo) {
            $nameMatch = $nameMatcher->findBestMatch($demo->player_name, $demo->user_id);

            $oldConfidence = $demo->name_confidence ?? 0;
            $oldUserId = $demo->suggested_user_id;

            // Update if confidence improved OR if user changed (even at same confidence)
            $shouldUpdate = ($nameMatch['confidence'] > $oldConfidence) ||
                            ($nameMatch['user_id'] != $oldUserId && $nameMatch['confidence'] >= $oldConfidence);

            if ($shouldUpdate) {
                $demo->update([
                    'name_confidence' => $nameMatch['confidence'],
                    'suggested_user_id' => $nameMatch['user_id'],
                ]);
                $improved++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Done! Improved confidence for {$improved} demos.");
    }
}
