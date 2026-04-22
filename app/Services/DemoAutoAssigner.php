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
     */
    public function attemptAssignToRecord(UploadedDemo $demo): string
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

                $record = Record::where('mapname', $demo->map_name)
                    ->where('gametype', $gametype)
                    ->where('time', $demo->time_ms)
                    ->where('user_id', $matchedUserId)
                    ->first();

                if ($record) {
                    // Scénár A: login + time agree → strongest match
                    $this->upgradeOfflineRecordToOnline($demo);

                    $demo->update([
                        'record_id'        => $record->id,
                        'status'           => 'assigned',
                        'name_confidence'  => 100,
                        'suggested_user_id'=> $matchedUserId,
                        'matched_alias'    => $loginMatch['matched_alias'],
                        'match_method'     => $tier . '_record',
                    ]);

                    Log::info('Demo auto-assigned to record via q3df login', [
                        'demo_id' => $demo->id,
                        'record_id' => $record->id,
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

        // PASS 1: uploader has a Record at this map/gametype/time.
        // Ignores name entirely — uploader identity is authoritative.
        if ($demo->user_id) {
            $uploaderRecord = Record::where('mapname', $demo->map_name)
                ->where('gametype', $gametype)
                ->where('time', $demo->time_ms)
                ->where('user_id', $demo->user_id)
                ->first();

            if ($uploaderRecord) {
                $this->upgradeOfflineRecordToOnline($demo);

                $demo->update([
                    'record_id'        => $uploaderRecord->id,
                    'status'           => 'assigned',
                    'name_confidence'  => 100,
                    'suggested_user_id'=> $demo->user_id,
                    'matched_alias'    => null,
                    'match_method'     => 'uploader_record',
                ]);

                Log::info('Demo auto-assigned to uploader\'s record', [
                    'demo_id' => $demo->id,
                    'record_id' => $uploaderRecord->id,
                    'user_id' => $demo->user_id,
                    'gametype' => $gametype,
                    'file_path' => $demo->file_path,
                ]);

                return self::OUTCOME_RECORD;
            }
        }

        // PASS 2: global fuzzy nick match across all user aliases.
        $nameMatch = $this->nameMatcher->findBestMatch($demo->player_name, null);

        $demo->update([
            'name_confidence'  => $nameMatch['confidence'],
            'suggested_user_id'=> $nameMatch['user_id'],
            'matched_alias'    => $nameMatch['matched_name'] ?? null,
        ]);

        Log::info('Name matching completed', [
            'demo_id' => $demo->id,
            'player_name' => $demo->player_name,
            'confidence' => $nameMatch['confidence'],
            'suggested_user_id' => $nameMatch['user_id'],
            'matched_alias' => $nameMatch['matched_name'] ?? null,
            'source' => $nameMatch['source'],
        ]);

        if ($nameMatch['confidence'] === 100 && $nameMatch['user_id']) {
            $record = Record::where('mapname', $demo->map_name)
                ->where('gametype', $gametype)
                ->where('time', $demo->time_ms)
                ->where('user_id', $nameMatch['user_id'])
                ->first();

            if ($record) {
                $this->upgradeOfflineRecordToOnline($demo);

                $demo->update([
                    'record_id'    => $record->id,
                    'status'       => 'assigned',
                    'match_method' => 'fuzzy_nick',
                ]);

                Log::info('Demo auto-assigned to record with 100% name match', [
                    'demo_id' => $demo->id,
                    'record_id' => $record->id,
                    'user_id' => $nameMatch['user_id'],
                    'gametype' => $demo->gametype,
                    'matched_alias' => $nameMatch['matched_name'] ?? null,
                    'file_path' => $demo->file_path,
                ]);

                return self::OUTCOME_RECORD;
            }
        }

        return self::OUTCOME_NONE;
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

        $demo->update([
            'name_confidence'  => $nameMatch['confidence'],
            'suggested_user_id'=> $nameMatch['user_id'],
            'matched_alias'    => $nameMatch['matched_name'] ?? null,
        ]);

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
