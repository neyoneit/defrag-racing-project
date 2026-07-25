<?php

namespace App\Services;

use App\Models\SftpCredential;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the storage-VPS `check <username>` health check for a credential
 * and persists the outcome onto the row (check_status, check_message,
 * last_checked_at, plus the demo stats the check returns for free).
 *
 * Never throws - provisioning flows call this right after creating an
 * account, and a broken *check* must not roll back or mask a successful
 * *provision*. A check that could not run is recorded as 'error'.
 */
class SftpCredentialChecker
{
    public function __construct(
        private readonly StorageVpsProvisioner $provisioner,
    ) {
    }

    /**
     * @return array{ok: bool, problems: array<int, string>}
     */
    public function check(SftpCredential $credential): array
    {
        try {
            $result = $this->provisioner->check($credential->sftp_username);
        } catch (Throwable $e) {
            Log::error('SFTP credential check failed to run', [
                'credential_id' => $credential->id,
                'sftp_username' => $credential->sftp_username,
                'error'         => $e->getMessage(),
            ]);

            $credential->update([
                'last_checked_at' => now(),
                'check_status'    => 'error',
                'check_message'   => 'Check could not run: ' . $e->getMessage(),
            ]);

            return ['ok' => false, 'problems' => ['check could not run: ' . $e->getMessage()]];
        }

        $ok       = (bool) ($result['ok'] ?? false);
        $problems = array_values((array) ($result['problems'] ?? []));

        $credential->update([
            'last_checked_at' => now(),
            'check_status'    => $ok ? 'ok' : 'failed',
            'check_message'   => $ok ? null : implode("\n", $problems),
            'demo_count'      => $result['demo_count'] ?? $credential->demo_count,
            'last_upload_at'  => isset($result['last_upload']) && $result['last_upload'] !== null
                ? \Illuminate\Support\Carbon::createFromTimestampUTC((int) $result['last_upload'])
                : $credential->last_upload_at,
        ]);

        if (!$ok) {
            Log::warning('SFTP credential check found problems', [
                'credential_id' => $credential->id,
                'sftp_username' => $credential->sftp_username,
                'problems'      => $problems,
            ]);
        }

        return ['ok' => $ok, 'problems' => $problems];
    }
}
