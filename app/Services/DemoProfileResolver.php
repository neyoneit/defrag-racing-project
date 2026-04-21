<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAlias;

/**
 * Resolves a demo (or any record-like object) to the registered defrag.racing
 * User profile, by comparing the demo's own identity signals against every
 * user's plain_name + approved aliases.
 *
 * Matching rules:
 *   - Colored signals always take priority (more unique)
 *       * Demo side:  q3df_login_name_colored, raw player_name (if it
 *                     still contains ^ color codes)
 *       * User side:  UserAlias.alias_colored
 *       * Comparison: exact match, case-sensitive, after trim
 *   - Plain fallback only when colored misses AND exactly one user owns the
 *     plain value (ambiguous plain — multiple users — is skipped)
 *       * Demo side:  player_name with ^ codes stripped, q3df_login_name
 *       * User side:  User.plain_name, UserAlias.alias
 *       * Comparison: lowercase + trim
 *
 * Buckets are preloaded once on first resolve() call and reused for the
 * lifetime of the instance, so resolving thousands of demos stays O(1)
 * per demo.
 */
class DemoProfileResolver
{
    /** @var array<string,string>|null plain-normalized value => profile key (only when unique) */
    protected ?array $plainBucket = null;
    /** @var array<string,string>|null colored value => profile key (only when unique) */
    protected ?array $coloredBucket = null;
    /** @var array<string,array<string>>|null plain value => [profile key, ...] (includes ambiguous) */
    protected ?array $plainBucketMulti = null;
    /** @var array<string,array<string>>|null colored value => [profile key, ...] (includes ambiguous) */
    protected ?array $coloredBucketMulti = null;

    /**
     * Return a profile key for a demo (or similar shape), or null if no
     * unique match. The key is either "user:<id>" (registered defrag.racing
     * account) or "mdd:<id>" (unclaimed q3df profile — the alias is linked
     * only via mdd_id because no local User has registered yet). Demos that
     * resolve to the same key cluster together even when their on-demo
     * signals differ.
     *
     * Accepts arrays or objects with optional keys:
     *   player_name, q3df_login_name, q3df_login_name_colored
     *
     * @param array|null $priorityKeys When plain signal is ambiguous (same
     *                   alias approved for multiple profiles), intersect
     *                   the candidates with this allow-list. If exactly one
     *                   candidate survives, resolve to them. Typical use:
     *                   pass the list of profile keys that own a main
     *                   record on the current map+physics so unrelated
     *                   namesakes on other maps don't disqualify the
     *                   legitimate owner.
     */
    public function resolve($demo, ?array $priorityKeys = null): ?string
    {
        $this->loadBuckets();

        // ---- colored pass (priority 1) --------------------------------
        $playerNameRaw = $this->get($demo, 'player_name');
        $q3dfColored = $this->get($demo, 'q3df_login_name_colored');

        if ($q3dfColored && ($v = trim($q3dfColored)) !== '' && isset($this->coloredBucket[$v])) {
            return $this->coloredBucket[$v];
        }
        // player_name is colored only if it actually contains color codes.
        if ($playerNameRaw && strpos($playerNameRaw, '^') !== false) {
            $v = trim($playerNameRaw);
            if ($v !== '' && isset($this->coloredBucket[$v])) {
                return $this->coloredBucket[$v];
            }
        }

        // ---- plain pass (priority 2) ----------------------------------
        $playerNamePlain = $this->normalizePlain(
            $playerNameRaw ? preg_replace('/\^[0-9\[\]]/', '', $playerNameRaw) : null
        );
        $q3dfPlain = $this->normalizePlain($this->get($demo, 'q3df_login_name'));

        foreach ([$playerNamePlain, $q3dfPlain] as $plain) {
            if ($plain === null) continue;
            if (isset($this->plainBucket[$plain])) {
                return $this->plainBucket[$plain];
            }
            // Ambiguous plain — try the priority allow-list as a tiebreaker.
            if ($priorityKeys !== null && isset($this->plainBucketMulti[$plain])) {
                $intersect = array_values(array_intersect($this->plainBucketMulti[$plain], $priorityKeys));
                if (count($intersect) === 1) {
                    return $intersect[0];
                }
            }
        }

        return null;
    }

    /**
     * Build the colored + plain lookup hashmaps. Plain values owned by more
     * than one user are dropped from the bucket so a fallback plain match
     * can't attach the demo to the wrong user.
     */
    protected function loadBuckets(): void
    {
        if ($this->plainBucket !== null) return;

        // Track occurrences per PROFILE KEY per value. Profile keys are
        // either "user:<id>" (registered defrag.racing account) or
        // "mdd:<id>" (unclaimed q3df profile — UserAlias row with user_id
        // NULL but mdd_id set, typical for scraped/imported aliases). Both
        // are treated as first-class cluster identities.
        //
        // "Count" uses row-count of matching approved aliases + plain_name
        // contribution from the user themselves. If two profiles both
        // claim "Bazz" but one of them has it approved 5× (across
        // different colored variants) and the other just once, the 5×
        // owner wins the ambiguous tiebreaker.
        $plainCounts = [];   // normalized plain value -> [profile key => count]
        $coloredCounts = []; // colored value -> [profile key => count]

        // User.plain_name — each user contributes their own plain_name
        // under their "user:<id>" key.
        User::query()
            ->whereNotNull('plain_name')
            ->where('plain_name', '!=', '')
            ->select(['id', 'plain_name'])
            ->get()
            ->each(function ($u) use (&$plainCounts) {
                $v = $this->normalizePlain($u->plain_name);
                if ($v !== null) {
                    $key = 'user:' . (int) $u->id;
                    $plainCounts[$v][$key] = ($plainCounts[$v][$key] ?? 0) + 1;
                }
            });

        // UserAlias — only approved aliases count. Prefer user_id when the
        // alias is linked to a local account, otherwise fall back to mdd_id
        // (scraped q3df profile that hasn't been claimed yet). Multiple
        // rows for the same (profile key, value) bump the count and
        // strengthen ownership in the ambiguity tiebreaker.
        UserAlias::query()
            ->where('is_approved', true)
            ->where(function ($q) {
                $q->whereNotNull('user_id')->orWhereNotNull('mdd_id');
            })
            ->select(['user_id', 'mdd_id', 'alias', 'alias_colored'])
            ->get()
            ->each(function ($a) use (&$plainCounts, &$coloredCounts) {
                $key = $a->user_id
                    ? ('user:' . (int) $a->user_id)
                    : ('mdd:' . (int) $a->mdd_id);
                if ($a->alias) {
                    $v = $this->normalizePlain($a->alias);
                    if ($v !== null) {
                        $plainCounts[$v][$key] = ($plainCounts[$v][$key] ?? 0) + 1;
                    }
                }
                if ($a->alias_colored) {
                    $v = trim($a->alias_colored);
                    if ($v !== '') {
                        $coloredCounts[$v][$key] = ($coloredCounts[$v][$key] ?? 0) + 1;
                    }
                }
            });

        // Collapse to resolved-owner hashmaps. Same rules as before:
        //   1. Only one profile claims the value → that profile wins.
        //   2. Multiple profiles claim it → the one with strictly more
        //      occurrences wins. Ties stay ambiguous and defer to the
        //      priority-list tiebreaker at resolve() time.
        $resolveOwner = function (array $keyCounts): ?string {
            if (count($keyCounts) === 1) {
                return (string) array_key_first($keyCounts);
            }
            $topKey = null; $topCount = -1; $tied = false;
            foreach ($keyCounts as $key => $cnt) {
                if ($cnt > $topCount) {
                    $topCount = $cnt; $topKey = (string) $key; $tied = false;
                } elseif ($cnt === $topCount) {
                    $tied = true;
                }
            }
            return $tied ? null : $topKey;
        };

        $plainBucket = []; $plainMulti = [];
        foreach ($plainCounts as $v => $keyCounts) {
            $plainMulti[$v] = array_keys($keyCounts);
            $owner = $resolveOwner($keyCounts);
            if ($owner !== null) $plainBucket[$v] = $owner;
        }
        $coloredBucket = []; $coloredMulti = [];
        foreach ($coloredCounts as $v => $keyCounts) {
            $coloredMulti[$v] = array_keys($keyCounts);
            $owner = $resolveOwner($keyCounts);
            if ($owner !== null) $coloredBucket[$v] = $owner;
        }

        $this->plainBucket = $plainBucket;
        $this->coloredBucket = $coloredBucket;
        $this->plainBucketMulti = $plainMulti;
        $this->coloredBucketMulti = $coloredMulti;
    }

    /**
     * Resolve many demos in one call (same preload). Returns
     * demo_id => user_id|null keyed by whatever 'id' field each input has.
     */
    public function resolveMany(iterable $demos): array
    {
        $this->loadBuckets();
        $out = [];
        foreach ($demos as $d) {
            $id = $this->get($d, 'id');
            if ($id === null) continue;
            $out[(int) $id] = $this->resolve($d);
        }
        return $out;
    }

    private function normalizePlain(?string $value): ?string
    {
        if ($value === null) return null;
        $v = strtolower(trim($value));
        return $v !== '' ? $v : null;
    }

    private function get($demo, string $key)
    {
        if (is_array($demo)) return $demo[$key] ?? null;
        return $demo->{$key} ?? null;
    }
}
