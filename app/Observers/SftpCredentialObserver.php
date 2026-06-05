<?php

namespace App\Observers;

use App\Models\Server;
use App\Models\SftpCredential;

class SftpCredentialObserver
{
    /**
     * Mirror the credential's declared servers into the `servers` table so
     * ScrapeServers picks them up alongside legacy manually-added rows.
     *
     * Dedupes by (ip, port): if a Server already exists at that address it's
     * linked and its rconpassword refreshed; otherwise a new Server row is
     * created with placeholders that the scraper will overwrite on first run.
     * Rows that disappear from the JSON are left in place — admin handles
     * removal manually from the Filament panel.
     */
    public function saved(SftpCredential $credential): void
    {
        if ($credential->status !== 'active') return;

        $declared = $credential->servers ?? [];
        if (empty($declared)) return;

        $owner = $credential->user;
        $ownerName = $owner?->plain_name ?: ($owner?->name ?: 'unknown');
        $ownerCountry = $owner?->country && $owner->country !== 'XX' ? $owner->country : '_404';

        foreach ($declared as $entry) {
            if (empty($entry['ip']) || empty($entry['port'])) continue;

            $existing = Server::where('ip', $entry['ip'])
                ->where('port', (int) $entry['port'])
                ->first();

            // Per-server location (set by owner in /server-hosting form or
            // by admin in Filament). Falls back to owner profile country
            // when blank — at least gives a flag instead of "_404".
            $entryLocation = !empty($entry['location']) ? strtoupper($entry['location']) : $ownerCountry;

            // Optional manual coordinates (admin fills these for servers whose
            // IP/hostname the auto-geolocator can't resolve). Only applied when
            // present, so they never wipe auto-filled values.
            $hasCoords = isset($entry['latitude'], $entry['longitude'])
                && $entry['latitude'] !== '' && $entry['longitude'] !== '';
            $entryLat = $hasCoords ? (float) $entry['latitude'] : null;
            $entryLon = $hasCoords ? (float) $entry['longitude'] : null;

            if ($existing) {
                // Don't clobber scraper-managed fields on existing rows;
                // just link to the credential, refresh the rcon password,
                // and propagate location updates (admin/owner can change it).
                $fill = [
                    'sftp_credential_id' => $credential->id,
                    'rconpassword'       => $entry['rcon'] ?? $existing->rconpassword,
                    'location'           => $entryLocation,
                ];
                if ($hasCoords) {
                    $fill['latitude'] = $entryLat;
                    $fill['longitude'] = $entryLon;
                }
                $existing->fill($fill)->save();
                continue;
            }

            Server::create([
                'ip'                 => $entry['ip'],
                'port'               => (int) $entry['port'],
                'sftp_credential_id' => $credential->id,
                'rconpassword'       => $entry['rcon'] ?? null,
                'name'               => 'Pending scrape',
                'plain_name'         => 'Pending scrape',
                'location'           => $entryLocation,
                'latitude'           => $entryLat,
                'longitude'          => $entryLon,
                'type'               => $entry['gametype'] ?? 'mixed',
                'admin_name'         => $ownerName,
                'map'                => '',
                'defrag'             => '',
                'besttime_country'   => '_404',
                'besttime_name'      => null,
                'besttime_url'       => '',
                'besttime_time'      => 0,
                'visible'            => true,
                'online'             => false,
            ]);
        }
    }
}
