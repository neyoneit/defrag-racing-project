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

            if ($existing) {
                // Don't clobber scraper-managed fields on existing rows;
                // just link to the credential and refresh the rcon password.
                $existing->fill([
                    'sftp_credential_id' => $credential->id,
                    'rconpassword'       => $entry['rcon'] ?? $existing->rconpassword,
                ])->save();
                continue;
            }

            Server::create([
                'ip'                 => $entry['ip'],
                'port'               => (int) $entry['port'],
                'sftp_credential_id' => $credential->id,
                'rconpassword'       => $entry['rcon'] ?? null,
                'name'               => 'Pending scrape',
                'plain_name'         => 'Pending scrape',
                'location'           => $ownerCountry,
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
