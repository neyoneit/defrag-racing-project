<?php

namespace App\Console\Commands;

use App\Models\SftpCredential;
use App\Services\StorageVpsProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncServerdemoStats extends Command
{
    protected $signature = 'serverdemos:sync-stats';

    protected $description = 'Pull per-account demo upload stats from the storage VPS into sftp_credentials (last_upload_at, demo_count)';

    public function handle(StorageVpsProvisioner $provisioner): int
    {
        try {
            $stats = $provisioner->stats();
        } catch (Throwable $e) {
            Log::error('serverdemos:sync-stats failed to reach storage VPS', [
                'error' => $e->getMessage(),
            ]);
            $this->error('Storage VPS unreachable: ' . $e->getMessage());

            return self::FAILURE;
        }

        $byUsername = collect($stats)->keyBy('username');
        $updated = 0;

        SftpCredential::query()
            ->whereIn('sftp_username', $byUsername->keys())
            ->get()
            ->each(function (SftpCredential $credential) use ($byUsername, &$updated) {
                $row = $byUsername[$credential->sftp_username];

                $credential->update([
                    'demo_count'     => (int) ($row['demo_count'] ?? 0),
                    'last_upload_at' => isset($row['last_upload']) && $row['last_upload'] !== null
                        ? Carbon::createFromTimestampUTC((int) $row['last_upload'])
                        : null,
                ]);
                $updated++;
            });

        $this->info("Synced upload stats for {$updated} credential(s).");

        return self::SUCCESS;
    }
}
