<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Jobs\GetLastMddRecords;
use App\Jobs\TournamentCalculationsJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {
    // mdd scrapes...
    // scrape online servers every minute
    $schedule->command('scrape:servers 0')->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();
    // scrape offline servers less frequently (every 5 minutes)
    $schedule->command('scrape:servers 1')->withoutOverlapping()->evenInMaintenanceMode()->everyFiveMinutes();
        $schedule->job(new GetLastMddRecords)->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();
        $schedule->command('scrape:maps')->withoutOverlapping()->evenInMaintenanceMode()->everyTwoMinutes();

        $schedule->job(new TournamentCalculationsJob)->withoutOverlapping()->evenInMaintenanceMode()->everyMinute();

        $schedule->command('tournaments:notifications-send')->everyTwoMinutes();

        // Calculate VQ3 and CPM ratings using Rust - all 8 categories (overall, rocket, plasma, grenade, slick, tele, bfg, strafe)
        // VQ3: ~89s, CPM: ~166s = ~4.5 minutes total for all 16 category rankings
        $schedule->command('ratings:calculate --physics=vq3')->withoutOverlapping()->daily();
        $schedule->command('ratings:calculate --physics=cpm')->withoutOverlapping()->daily();

        // Cache WR/Top3 counts for clan statistics (updates cached_wr_count and cached_top3_count on users table)
        $schedule->command('rankings:cache')->withoutOverlapping()->hourly();

        // Check Twitch live status every 2 minutes
        $schedule->command('twitch:check-live-status')->withoutOverlapping()->everyTwoMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
