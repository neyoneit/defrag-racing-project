<?php

namespace App\Services;

use App\Models\DefragliveChatMessage;
use App\Models\DefragliveServerState;

/**
 * Rebuilds the public console.json / serverstate.json from the DB.
 *
 * This is the drop-in replacement for the legacy cron `docker cp` that copied
 * those files out of the bridge container. The current Twitch extension reads
 * https://tw.defrag.racing/{console,serverstate}.json over HTTP for its
 * initial load + polling, so as long as we write the SAME files, at the SAME
 * path, in the SAME shape, the extension keeps working unchanged - no Twitch
 * review needed while the new extension is being built.
 *
 * Disabled (no-op) until services.defraglive.public_path is set, so it can't
 * fight the legacy cron before the VPS cutover.
 */
class DefragliveJsonWriter
{
    /** @return bool true if files were written, false if the writer is disabled. */
    public function write(): bool
    {
        $dir = config('services.defraglive.public_path');

        if (!$dir) {
            return false;
        }

        $dir = rtrim($dir, '/');

        $this->writeConsole($dir);
        $this->writeServerState($dir);

        return true;
    }

    private function writeConsole(string $dir): void
    {
        $limit = (int) config('services.defraglive.console_limit', 100);

        // Last N non-deleted rows in arrival order, payloads verbatim - the
        // same array-of-broadcast-objects shape the bridge's console.json had.
        // Soft-deleted (moderated) rows are dropped by the SoftDeletes scope.
        $payloads = DefragliveChatMessage::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('payload')
            ->reverse()
            ->values()
            ->all();

        $this->atomicWrite($dir . '/console.json', $payloads);
    }

    private function writeServerState(string $dir): void
    {
        $state = DefragliveServerState::find(1);

        // Empty state must serialise as {} (object), not [] - the extension
        // spreads it into its serverstate reducer.
        $this->atomicWrite($dir . '/serverstate.json', $state?->payload ?? new \stdClass());
    }

    private function atomicWrite(string $path, $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Write to a temp file then rename so a reader (the extension polling
        // the file) never sees a half-written document.
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $json, LOCK_EX);
        rename($tmp, $path);
    }
}
