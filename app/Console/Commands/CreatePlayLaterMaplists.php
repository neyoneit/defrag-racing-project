<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Maplist;

class CreatePlayLaterMaplists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maplists:create-play-later';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Play Later maplists for existing users who don\'t have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating Play Later maplists for existing users...');

        // Get all users who don't have a Play Later maplist
        $users = User::whereDoesntHave('maplists', function ($query) {
            $query->where('is_play_later', true);
        })->get();

        $count = 0;
        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            Maplist::create([
                'user_id' => $user->id,
                'name' => 'Play Later',
                'description' => 'Save maps to play later',
                'is_public' => false,
                'is_play_later' => true,
            ]);
            $count++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully created Play Later maplists for {$count} users.");

        return Command::SUCCESS;
    }
}
