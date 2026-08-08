<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\External\WorldSpawn;
use App\Models\Map;
use App\Services\NewMapNotifier;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScrapeMaps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:maps';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrapes all the latest maps';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lastMap = Map::orderBy('date_added', 'DESC')->first();

        if (! $lastMap ) {
            return;
        }

        $ws = new WorldSpawn();

        $maps = $ws->scrape_until($lastMap->name);

        $inserted = new Collection();

        foreach($maps as $map) {
            $new = $this->insertNewMap($map);

            if ($new) {
                $inserted->push($new);
            }
        }

        // One notification for the whole run, not one per map - Worldspawn
        // publishes in bursts and the scrape above picks a burst up whole.
        if ($inserted->isNotEmpty()) {
            $sent = app(NewMapNotifier::class)->notify($inserted);

            $this->info("Notified {$sent} users about {$inserted->count()} new map(s)");
        }
    }

    public function insertNewMap($map) {
        $exists = Map::where('name', $map['name'])->exists();

        if ($exists) {
            return null;
        }

        $newMap = new Map();

        $newMap->fill($map);

        $newMap->thumbnail = $this->downloadImage($map['map_image']);
        $newMap->visible = true;

        $newMap->save();

        $this->info("Added Map : " . $map['name']);

        return $newMap;
    }

    public function downloadImage($url) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($ch);

        curl_close($ch);

        $extension = pathinfo($url, PATHINFO_EXTENSION);
        $filename = "thumbs/" . Str::random(20) . '.' . $extension;

        Storage::disk('public')->put($filename, $data);

        return $filename;
    }
}
