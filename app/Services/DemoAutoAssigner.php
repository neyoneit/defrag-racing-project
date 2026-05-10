<?php

namespace App\Services;

use App\Models\UploadedDemo;
use App\Models\Record;
use App\Models\OfflineRecord;
use Illuminate\Support\Facades\Log;

/**
 * Shared auto-assign pipeline for online demos.
 *
 * Extracted from DemoProcessorService::autoAssignToRecord so that both the
 * live upload path (DemoProcessorService) and the nightly batch rematch
 * (RematchAllDemos) run the same matching logic — PASS 0 (q3df login),
 * PASS 1 (uploader record), PASS 2 (fuzzy nick) — and write the same
 * match_method values.
 *
 * Does NOT create offline records. Caller is responsible for fall-through
 * handling:
 *   - OUTCOME_RECORD  : demo is fully assigned; nothing more to do
 *   - OUTCOME_PROFILE : user_id was upgraded via q3df login but no Record
 *                       exists; caller should create an offline_record
 *   - OUTCOME_NONE    : no match. Name-match fields may have been updated.
 *                       Processor creates offline_record; rematch may
 *                       create one for offline demos with 100% name match.
 */
class DemoAutoAssigner
{
    public const OUTCOME_RECORD  = 'record';
    public const OUTCOME_PROFILE = 'profile';
    public const OUTCOME_NONE    = 'none';

    public function __construct(protected NameMatcher $nameMatcher) {}

    /**
     * Run the 3-pass auto-assign pipeline on an ONLINE demo. For offline
     * demos this returns OUTCOME_NONE without touching the demo.
     *
     * When $ctx is supplied (batch rematch), Record lookups go through an
     * in-memory hashmap instead of per-demo SELECTs. Live upload path
     * passes null and falls back to direct queries.
     */
    public function attemptAssignToRecord(UploadedDemo $demo, ?DemoAutoAssignContext $ctx = null): string
    {
        if (!$demo->map_name || !$demo->physics || !$demo->time_ms || !$demo->player_name) {
            return self::OUTCOME_NONE;
        }

        // Online demos are m-prefixed gametypes (mdf, mfs, mfc). Offline
        // demos (df, fs, fc) have their own leaderboards and are never
        // matched to online Records.
        if ($demo->gametype && !str_starts_with($demo->gametype, 'm')) {
            Log::info('Skipping auto-assign for offline demo', [
                'demo_id' => $demo->id,
                'gametype' => $demo->gametype,
                'map' => $demo->map_name,
            ]);
            return self::OUTCOME_NONE;
        }

        $physics = str_replace('.tr', '', strtolower($demo->physics));
        $gametype = 'run_' . $physics;

        // PASS 0: q3df login — server-side authoritative identifier from
        // console messages ("Enter(Enter), you are now rank..."). Colored
        // variant is effectively unique; plain variant is accepted only
        // when exactly one user owns it.
        if ($demo->q3df_login_name || $demo->q3df_login_name_colored) {
            $loginMatch = $this->nameMatcher->matchByQ3dfLogin(
                $demo->q3df_login_name,
                $demo->q3df_login_name_colored
            );

            if ($loginMatch['user_id']) {
                $matchedUserId = $loginMatch['user_id'];
                $tier = $loginMatch['tier']; // 'q3df_colored' | 'q3df_plain'

                $recordId = $this->findRecordId($ctx, $demo->map_name, $gametype, (int) $demo->time_ms, (int) $matchedUserId);

                if ($recordId !== null) {
                    // Scénár A: login + time agree → strongest match
                    $this->upgradeOfflineRecordToOnline($demo);

                    $demo->update([
                        'record_id'        => $recordId,
                        'status'           => 'assigned',
                        'name_confidence'  => 100,
                        'suggested_user_id'=> $matchedUserId,
                        'matched_alias'    => $loginMatch['matched_alias'],
                        'match_method'     => $tier . '_record',
                    ]);

                    Log::info('Demo auto-assigned to record via q3df login', [
                        'demo_id' => $demo->id,
                        'record_id' => $recordId,
                        'user_id' => $matchedUserId,
                        'tier' => $tier,
                        'matched_alias' => $loginMatch['matched_alias'],
                    ]);

                    return self::OUTCOME_RECORD;
                }

                // Scénár B: login matches a user but no Record with this
                // time. Attribute demo to that profile; caller creates an
                // offline_record so the run appears on that user's leaderboard.
                $demo->update([
                    'user_id'          => $matchedUserId,
                    'name_confidence'  => 100,
                    'suggested_user_id'=> $matchedUserId,
                    'matched_alias'    => $loginMatch['matched_alias'],
                    'match_method'     => $tier . '_profile',
                ]);

                Log::info('Demo auto-assigned to profile via q3df login (no record match)', [
                    'demo_id' => $demo->id,
                    'user_id' => $matchedUserId,
                    'tier' => $tier,
                    'matched_alias' => $loginMatch['matched_alias'],
                ]);

                return self::OUTCOME_PROFILE;
            }
        }

        // PASS 1: uploader has a Record at this map/gametype/time AND the
        // demo's player_name actually resolves to that uploader via the
        // alias system. The name verification guards against bulk-uploader
        // accounts (e.g. an admin importing third-party demos): without it,
        // any cizí demo with a time matching one of the uploader's own
        // Records would be wrongly stamped as the uploader's run.
        if ($demo->user_id) {
            $uploaderRecordId = $this->findRecordId($ctx, $demo->map_name, $gametype, (int) $demo->time_ms, (int) $demo->user_id);

            if ($uploaderRecordId !== null) {
                $nameMatch = $this->nameMatcher->findBestMatch($demo->player_name, null);
                $playerMatchesUploader = ($nameMatch['confidence'] === 100 && (int) $nameMatch['user_id'] === (int) $demo->user_id);

                if ($playerMatchesUploader) {
                    $this->upgradeOfflineRecordToOnline($demo);

                    $demo->update([
                        'record_id'        => $uploaderRecordId,
                        'status'           => 'assigned',
                        'name_confidence'  => 100,
                        'suggested_user_id'=> $demo->user_id,
                        'matched_alias'    => $nameMatch['matched_name'] ?? null,
                        'match_method'     => 'uploader_record',
                    ]);

                    Log::info('Demo auto-assigned to uploader\'s record (name verified)', [
                        'demo_id' => $demo->id,
                        'record_id' => $uploaderRecordId,
                        'user_id' => $demo->user_id,
                        'matched_alias' => $nameMatch['matched_name'] ?? null,
                        'gametype' => $gametype,
                    ]);

                    return self::OUTCOME_RECORD;
                }

                Log::info('Pass 1 skipped: uploader has matching record but demo player_name does not resolve to uploader', [
                    'demo_id' => $demo->id,
                    'uploader_user_id' => $demo->user_id,
                    'player_name' => $demo->player_name,
                    'name_match_user_id' => $nameMatch['user_id'] ?? null,
                    'name_match_confidence' => $nameMatch['confidence'] ?? null,
                    'candidate_record_id' => $uploaderRecordId,
                ]);
                // Fall through to Pass 2 (fuzzy nick) — that path will
                // attribute the demo to the actual player.
            }
        }

        // PASS 2: global fuzzy nick match across all user aliases.
        $nameMatch = $this->nameMatcher->findBestMatch($demo->player_name, null);

        // Skip no-op writes: rematch runs daily; if aliases didn't change,
        // most demos already have the correct name-match hints. Writing
        // the same values back wastes ~80% of UPDATE queries in batch.
        $newAlias = $nameMatch['matched_name'] ?? null;
        if ($demo->name_confidence !== $nameMatch['confidence']
            || $demo->suggested_user_id !== $nameMatch['user_id']
            || $demo->matched_alias !== $newAlias) {
            $demo->update([
                'name_confidence'  => $nameMatch['confidence'],
                'suggested_user_id'=> $nameMatch['user_id'],
                'matched_alias'    => $newAlias,
            ]);

            Log::info('Name matching completed', [
                'demo_id' => $demo->id,
                'player_name' => $demo->player_name,
                'confidence' => $nameMatch['confidence'],
                'suggested_user_id' => $nameMatch['user_id'],
                'matched_alias' => $newAlias,
                'source' => $nameMatch['source'],
            ]);
        }

        if ($nameMatch['confidence'] === 100 && $nameMatch['user_id']) {
            $recordId = $this->findRecordId($ctx, $demo->map_name, $gametype, (int) $demo->time_ms, (int) $nameMatch['user_id']);

            if ($recordId !== null) {
                $this->upgradeOfflineRecordToOnline($demo);

                $demo->update([
                    'record_id'    => $recordId,
                    'status'       => 'assigned',
                    'match_method' => 'fuzzy_nick',
                ]);

                Log::info('Demo auto-assigned to record with 100% name match', [
                    'demo_id' => $demo->id,
                    'record_id' => $recordId,
                    'user_id' => $nameMatch['user_id'],
                    'gametype' => $demo->gametype,
                    'matched_alias' => $newAlias,
                    'file_path' => $demo->file_path,
                ]);

                return self::OUTCOME_RECORD;
            }
        }

        return self::OUTCOME_NONE;
    }

    /**
     * O(1) Record lookup via context hashmap, or ad-hoc SELECT when no
     * context is supplied (live upload path).
     */
    protected function findRecordId(?DemoAutoAssignContext $ctx, string $mapname, string $gametype, int $timeMs, int $userId): ?int
    {
        if ($ctx !== null) {
            return $ctx->findRecordId($mapname, $gametype, $timeMs, $userId);
        }
        $r = Record::where('mapname', $mapname)
            ->where('gametype', $gametype)
            ->where('time', $timeMs)
            ->where('user_id', $userId)
            ->first(['id']);
        return $r?->id;
    }

    /**
     * Run only the global fuzzy nick match and persist the name-match
     * fields. Used by the batch rematch for offline demos, which never
     * assign to online Records but still benefit from keeping the
     * suggested-user / alias hint fresh when new aliases are approved.
     *
     * @return array{user_id: int|null, confidence: int, source: string, matched_name: string|null}
     */
    public function updateNameMatchOnly(UploadedDemo $demo): array
    {
        $nameMatch = $this->nameMatcher->findBestMatch($demo->player_name, null);

        $newAlias = $nameMatch['matched_name'] ?? null;
        if ($demo->name_confidence !== $nameMatch['confidence']
            || $demo->suggested_user_id !== $nameMatch['user_id']
            || $demo->matched_alias !== $newAlias) {
            $demo->update([
                'name_confidence'  => $nameMatch['confidence'],
                'suggested_user_id'=> $nameMatch['user_id'],
                'matched_alias'    => $newAlias,
            ]);
        }

        return $nameMatch;
    }

    /**
     * If the demo already has an offline_record (from a prior
     * fallback-assignment), delete it and decrement ranks of any slower
     * offline records for the same map/physics/gametype. No-op when no
     * offline_record exists — safe to call from the live processor path
     * where there's never a pre-existing offline_record for a demo.
     */
    protected function upgradeOfflineRecordToOnline(UploadedDemo $demo): void
    {
        $offlineRecord = OfflineRecord::where('demo_id', $demo->id)->first();
        if (!$offlineRecord) {
            return;
        }

        OfflineRecord::where('map_name', $offlineRecord->map_name)
            ->where('physics', $offlineRecord->physics)
            ->where('gametype', $offlineRecord->gametype)
            ->where('time_ms', '>', $offlineRecord->time_ms)
            ->decrement('rank');

        $offlineRecordId = $offlineRecord->id;
        $offlineRecord->delete();

        Log::info('Upgraded offline_record to online record (deleted offline_record)', [
            'demo_id' => $demo->id,
            'offline_record_id' => $offlineRecordId,
        ]);
    }
}
