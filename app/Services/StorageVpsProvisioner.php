<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Wraps SSH calls to the storage VPS provisioning CLI.
 *
 * The remote side is restricted by `command=` in authorized_keys to a
 * single binary (`df-sftp-provision-wrap`), so the only attack surface
 * here is whatever the wrapper itself accepts. The wrapper rejects any
 * subcommand outside `create|reset-password|revoke|info|list` and any
 * argument that isn't `[a-zA-Z0-9_-]+`, but we still validate
 * client-side — defense in depth.
 *
 * All methods return the decoded JSON array from the remote command.
 * On any failure (non-zero exit, malformed JSON, SSH timeout) they
 * throw RuntimeException with a context-rich message.
 */
class StorageVpsProvisioner
{
    private const USERNAME_RE = '/^[a-z][a-z0-9_-]{2,31}$/';

    /** @var int  How long to wait for ssh+remote+useradd to finish. */
    private const PROCESS_TIMEOUT_SECONDS = 30;

    public function create(string $username): array
    {
        return $this->call('create', [$username]);
    }

    public function resetPassword(string $username): array
    {
        return $this->call('reset-password', [$username]);
    }

    public function revoke(string $username): array
    {
        return $this->call('revoke', [$username]);
    }

    public function info(string $username): array
    {
        return $this->call('info', [$username]);
    }

    /** @return array<int, string> */
    public function list(): array
    {
        return $this->call('list', []);
    }

    /**
     * Build a candidate SFTP username from a defrag.racing user. Strips
     * to lowercase alphanumeric+dash, prefixes 'df-' if the result
     * doesn't start with a letter (Unix requires letter-first), and
     * appends a short suffix to disambiguate.
     */
    public static function suggestUsername(string $display): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9-]/i', '', $display));
        $base = trim($base, '-');

        if ($base === '' || !preg_match('/^[a-z]/', $base)) {
            $base = 'df-' . $base;
        }

        // Cap so we have room for a suffix; Unix usernames cap at 32.
        $base = substr($base, 0, 24);

        return $base;
    }

    /**
     * @param array<int, string> $args
     */
    private function call(string $subcommand, array $args): array
    {
        foreach ($args as $arg) {
            if (!preg_match(self::USERNAME_RE, $arg)) {
                throw new RuntimeException(
                    "Refusing to call '$subcommand': argument '$arg' fails username validation"
                );
            }
        }

        $command = trim($subcommand . ' ' . implode(' ', $args));

        $process = new Process([
            'ssh',
            '-i', config('services.storage_vps.key_path'),
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=10',
            '-p', (string) config('services.storage_vps.port'),
            sprintf(
                '%s@%s',
                config('services.storage_vps.user'),
                config('services.storage_vps.host')
            ),
            $command,
        ]);
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);

        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning('StorageVpsProvisioner ssh failed', [
                'subcommand' => $subcommand,
                'args'       => $args,
                'exit_code'  => $process->getExitCode(),
                'stderr'     => $process->getErrorOutput(),
            ]);

            throw new RuntimeException(sprintf(
                'storage VPS call "%s" failed (exit %d): %s',
                $command,
                $process->getExitCode(),
                trim($process->getErrorOutput()) ?: 'no stderr',
            ));
        }

        // The remote CLI emits human log lines to stderr and a single
        // JSON line to stdout — we only consume stdout.
        $stdout = trim($process->getOutput());
        if ($stdout === '') {
            throw new RuntimeException("storage VPS call '$command' returned empty stdout");
        }

        $decoded = json_decode($stdout, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(
                "storage VPS call '$command' returned non-JSON: " . substr($stdout, 0, 200)
            );
        }

        return $decoded;
    }
}
