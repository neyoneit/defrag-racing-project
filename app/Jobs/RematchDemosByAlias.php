<?php

namespace App\Jobs;

use App\Models\UserAlias;
use App\Models\UploadedDemo;
use App\Services\NameMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RematchDemosByAlias implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $alias;

    /**
     * Create a new job instance.
     */
    public function __construct(UserAlias $alias)
    {
        $this->alias = $alias;
    }

    /**
     * Execute the job.
     *
     * Re-match demos that might now match with the new alias
     */
    public function handle(): void
    {
        Log::info('RematchDemosByAlias job started', [
            'alias_id' => $this->alias->id,
            'alias' => $this->alias->alias,
            'user_id' => $this->alias->user_id,
        ]);

        $nameMatcher = app(NameMatcher::class);

        // Get demos that don't have 100% confidence or have no user match
        // These are candidates for rematching with the new alias
        $demos = UploadedDemo::where(function ($query) {
            $query->where('name_confidence', '<', 100)
                  ->orWhereNull('name_confidence')
                  ->orWhereNull('suggested_user_id');
        })
        ->whereNotNull('player_name')
        ->get();

        $rematchedCount = 0;

        foreach ($demos as $demo) {
            // Re-run name matching
            $nameMatch = $nameMatcher->findBestMatch($demo->player_name, $demo->user_id);

            // Only update if we found a better match
            if ($nameMatch['user_id'] === $this->alias->user_id &&
                $nameMatch['confidence'] > ($demo->name_confidence ?? 0)) {

                $demo->update([
                    'name_confidence' => $nameMatch['confidence'],
                    'suggested_user_id' => $nameMatch['user_id'],
                ]);

                $rematchedCount++;

                Log::info('Demo rematched with new alias', [
                    'demo_id' => $demo->id,
                    'player_name' => $demo->player_name,
                    'old_confidence' => $demo->name_confidence,
                    'new_confidence' => $nameMatch['confidence'],
                    'alias' => $this->alias->alias,
                ]);
            }
        }

        Log::info('RematchDemosByAlias job completed', [
            'alias_id' => $this->alias->id,
            'demos_checked' => $demos->count(),
            'demos_rematched' => $rematchedCount,
        ]);
    }
}
