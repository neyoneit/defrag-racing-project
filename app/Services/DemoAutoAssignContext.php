<?php

namespace App\Services;

use App\Models\Record;
use Illuminate\Support\Facades\DB;

/**
 * Preloaded lookup tables for batch auto-assign runs.
 *
 * A nightly demos:rematch-all pass would otherwise issue two Record
 * lookups per demo (up to ~212k demos × ~4 lookups including q3df login,
 * uploader record, and fuzzy match Record). Building one in-memory
 * hashmap up front turns each of those into an O(1) array access,
 * eliminating the dominant DB cost of the batch.
 *
 * Only passed explicitly in the batch path — live upload (ProcessDemoJob)
 * processes one demo at a time where ad-hoc queries are perfectly fine
 * and loading ~650k records into memory would be wasteful.
 */
class DemoAutoAssignContext
{
    /**
     * Key format: "mapname|gametype|time_ms|user_id" => record_id.
     *
     * Only (mapname, gametype, time, user_id) is indexed because that's
     * the exact 4-tuple every auto-assign PASS looks up. Storing only the
     * id keeps the hashmap compact (~100 bytes per entry vs a hydrated
     * Eloquent model); callers only need the id for $demo->record_id
     * assignment.
     *
     * @param array<string, int> $recordIndex
     */
    public function __construct(protected array $recordIndex) {}

    /**
     * Build the context by streaming Records in chunks so the full
     * collection never sits in memory at once.
     */
    public static function build(): self
    {
        $index = [];
        DB::table('records')
            ->select(['id', 'mapname', 'gametype', 'time', 'user_id'])
            ->orderBy('id')
            ->chunk(20000, function ($chunk) use (&$index) {
                foreach ($chunk as $r) {
                    if ($r->user_id === null) {
                        continue;
                    }
                    $key = $r->mapname . '|' . $r->gametype . '|' . $r->time . '|' . $r->user_id;
                    $index[$key] = (int) $r->id;
                }
            });
        return new self($index);
    }

    public function findRecordId(string $mapname, string $gametype, int $timeMs, int $userId): ?int
    {
        return $this->recordIndex[$mapname . '|' . $gametype . '|' . $timeMs . '|' . $userId] ?? null;
    }

    public function recordCount(): int
    {
        return count($this->recordIndex);
    }
}
