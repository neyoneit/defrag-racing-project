<?php

namespace App\Console\Commands;

use App\Models\RenderedVideo;
use App\Models\SiteSetting;
use Illuminate\Console\Command;

class DemomeAutoPublish extends Command
{
    protected $signature = 'demome:auto-publish';

    protected $description = 'Weekly auto-publish: mark all unlisted auto-rendered videos for publishing';

    public function handle()
    {
        $count = RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->where('publish_approved', false)
            ->whereNull('published_at')
            ->whereNotNull('youtube_video_id')
            ->count();

        if ($count === 0) {
            $this->info('No unlisted videos to publish.');
            return;
        }

        RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->where('publish_approved', false)
            ->whereNull('published_at')
            ->whereNotNull('youtube_video_id')
            ->update(['publish_approved' => true]);

        // Store when the last auto-publish happened
        SiteSetting::set('demome:last_auto_publish', now()->toIso8601String());

        $this->info("Marked {$count} unlisted videos for publishing.");
    }
}
