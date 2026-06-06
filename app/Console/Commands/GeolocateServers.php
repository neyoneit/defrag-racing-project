<?php

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fill in latitude/longitude for servers that don't have them yet, by looking
 * up their IP via the free ip-api.com batch endpoint (no key needed).
 *
 * Only touches servers where a coordinate is still missing, so a value set by
 * hand (or filled on a previous run) is never overwritten. Scheduled, so new
 * servers get geolocated automatically without anyone editing them.
 */
class GeolocateServers extends Command
{
    protected $signature = 'servers:geolocate {--all : Re-geolocate every server, overwriting existing coordinates}';

    protected $description = 'Auto-fill server latitude/longitude from their IP (only missing ones by default)';

    /**
     * Don't re-hit the geo API for an unresolvable server more often than this.
     * Below the daily schedule interval so the once-a-day run always retries
     * failures (rather than skipping a day on timing drift).
     */
    private const RETRY_AFTER_HOURS = 12;

    public function handle(): int
    {
        $query = Server::query();
        if (! $this->option('all')) {
            // Still missing coordinates AND either never tried or last tried long
            // enough ago. New servers (geo_checked_at null) get picked up at once;
            // ones the API couldn't resolve are retried at most once a day, not
            // every hour forever.
            $cutoff = now()->subHours(self::RETRY_AFTER_HOURS);
            $query->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
                ->where(fn ($q) => $q->whereNull('geo_checked_at')->orWhere('geo_checked_at', '<=', $cutoff));
        }
        $servers = $query->get();

        if ($servers->isEmpty()) {
            $this->info('No servers need geolocation.');

            return self::SUCCESS;
        }

        $this->info("Geolocating {$servers->count()} server(s)...");
        $updated = 0;

        // ip-api batch: up to 100 queries per POST. Key results by the bare IP.
        foreach ($servers->chunk(100) as $chunk) {
            $ips = $chunk->map(fn ($s) => $this->resolveIp($s->ip))->unique()->values()->all();

            try {
                $resp = Http::timeout(20)->post('http://ip-api.com/batch?fields=status,lat,lon,query', $ips);
            } catch (\Throwable $e) {
                $this->error('ip-api request failed: '.$e->getMessage());

                continue;
            }

            if (! $resp->ok()) {
                $this->error('ip-api returned HTTP '.$resp->status());

                continue;
            }

            // Map IP -> [lat, lon] for successful lookups.
            $byIp = [];
            foreach ($resp->json() ?? [] as $row) {
                if (($row['status'] ?? '') === 'success' && isset($row['lat'], $row['lon'], $row['query'])) {
                    $byIp[$row['query']] = [$row['lat'], $row['lon']];
                }
            }

            foreach ($chunk as $server) {
                // Stamp the attempt regardless of outcome so an unresolvable IP
                // backs off to the daily cadence instead of retrying hourly.
                $server->geo_checked_at = now();

                $coords = $byIp[$this->resolveIp($server->ip)] ?? null;
                if (! $coords) {
                    $server->save();
                    $this->warn("  - {$server->plain_name} ({$server->ip}): not resolved");

                    continue;
                }

                $server->latitude = $coords[0];
                $server->longitude = $coords[1];
                $server->save();
                $updated++;
                $this->line("  + {$server->plain_name} ({$server->ip}): {$coords[0]}, {$coords[1]}");
            }
        }

        $this->info("Done. Updated {$updated} server(s).");

        return self::SUCCESS;
    }

    /**
     * Strip any :port and resolve a hostname to an IP - ip-api's batch endpoint
     * only accepts IPs, not hostnames (e.g. deimos.baseq.fr). gethostbyname
     * returns the host unchanged on DNS failure, which ip-api then reports as a
     * miss, so the server just stays without coordinates.
     */
    private function resolveIp(string $ip): string
    {
        $host = explode(':', trim($ip))[0];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        return gethostbyname($host);
    }
}
