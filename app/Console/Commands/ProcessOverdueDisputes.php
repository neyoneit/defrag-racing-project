<?php

namespace App\Console\Commands;

use App\Models\ChallengeDispute;
use App\Models\HeadhunterChallenge;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessOverdueDisputes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'headhunter:process-disputes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process overdue challenge disputes and ban creators who haven\'t responded within 14 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing overdue challenge disputes...');

        // Find all pending disputes that are over 14 days old without a response
        $overdueDisputes = ChallengeDispute::where('status', 'pending')
            ->whereNull('creator_responded_at')
            ->where('created_at', '<=', now()->subDays(14))
            ->with(['creator', 'challenge'])
            ->get();

        if ($overdueDisputes->isEmpty()) {
            $this->info('No overdue disputes found.');
            return 0;
        }

        $this->info("Found {$overdueDisputes->count()} overdue dispute(s).");

        $bannedCreatorIds = [];

        DB::transaction(function () use ($overdueDisputes, &$bannedCreatorIds) {
            foreach ($overdueDisputes as $dispute) {
                // Update dispute status
                $dispute->update([
                    'status' => 'auto_banned',
                    'resolved_at' => now(),
                    'admin_notes' => 'Automatically banned - no response within 14 days'
                ]);

                // Ban the creator from creating new challenges
                if (!in_array($dispute->creator_id, $bannedCreatorIds)) {
                    HeadhunterChallenge::where('creator_id', $dispute->creator_id)
                        ->update(['creator_banned' => true]);

                    $bannedCreatorIds[] = $dispute->creator_id;

                    // Notify the creator
                    $dispute->creator->systemNotify(
                        'headhunter_ban',
                        'You have been banned from creating Headhunter challenges',
                        'You failed to respond to a dispute within 14 days for challenge "' . $dispute->challenge->title . '".',
                        route('headhunter.show', $dispute->challenge_id)
                    );

                    $this->warn("Banned creator: {$dispute->creator->username} (ID: {$dispute->creator_id})");
                }

                // Notify the claimer
                $dispute->claimer->systemNotify(
                    'headhunter_dispute_resolved',
                    'Your dispute has been automatically resolved',
                    'The creator failed to respond within 14 days for challenge "' . $dispute->challenge->title . '". They have been banned from creating new challenges.',
                    route('headhunter.show', $dispute->challenge_id)
                );
            }
        });

        $this->info("Successfully processed {$overdueDisputes->count()} dispute(s) and banned " . count($bannedCreatorIds) . " creator(s).");

        return 0;
    }
}
