<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Groups uploaded demos into virtual players based on matching any of:
 *   - player_name (case-insensitive, color codes stripped)
 *   - q3df_login_name_colored (exact match)
 *   - q3df_login_name (plain, exact match)
 *
 * Matches are transitive: A↔B via colored and B↔C via plain means {A,B,C} are
 * the same virtual player. Implemented as a simple union-find over bucket keys,
 * one bucket per distinct value in each signal. A NULL / empty signal is not a
 * match and does not unite anything.
 */
class VirtualPlayerGrouper
{
    /**
     * Find the equivalence class (collection of demos) that contains the seed.
     * The collection is sorted by time_ms ASC (fastest first).
     *
     * @param iterable $demos Items with at least: id, time_ms, player_name,
     *                        q3df_login_name, q3df_login_name_colored
     * @param object|array $seed The seed demo to match against (same shape).
     * @return Collection
     */
    public function classFor(iterable $demos, $seed): Collection
    {
        $list = collect($demos)->values();
        $seedKey = $this->demoKey($seed);
        if ($seedKey === null) {
            return collect();
        }

        // Union-find parent map keyed by demo index
        $parent = [];
        for ($i = 0; $i < $list->count(); $i++) {
            $parent[$i] = $i;
        }

        // Bucket each signal value -> first demo index we saw it on. When we see
        // the same value on a later demo, union them. O(n) in total.
        $seenName = [];
        $seenColored = [];
        $seenPlain = [];

        $indexByKey = [];

        foreach ($list as $i => $demo) {
            $key = $this->demoKey($demo);
            if ($key) {
                $indexByKey[$key] = $i;
            }

            $nameNorm = $this->normName($this->get($demo, 'player_name'));
            $coloredNorm = $this->normColored($this->get($demo, 'q3df_login_name_colored'));
            $plainNorm = $this->normPlain($this->get($demo, 'q3df_login_name'));

            if ($nameNorm !== null) {
                if (isset($seenName[$nameNorm])) {
                    $this->union($parent, $seenName[$nameNorm], $i);
                } else {
                    $seenName[$nameNorm] = $i;
                }
            }
            if ($coloredNorm !== null) {
                if (isset($seenColored[$coloredNorm])) {
                    $this->union($parent, $seenColored[$coloredNorm], $i);
                } else {
                    $seenColored[$coloredNorm] = $i;
                }
            }
            if ($plainNorm !== null) {
                if (isset($seenPlain[$plainNorm])) {
                    $this->union($parent, $seenPlain[$plainNorm], $i);
                } else {
                    $seenPlain[$plainNorm] = $i;
                }
            }
        }

        if (!isset($indexByKey[$seedKey])) {
            return collect();
        }

        $seedIdx = $indexByKey[$seedKey];
        $seedRoot = $this->find($parent, $seedIdx);

        $matched = $list->filter(fn ($_d, $i) => $this->find($parent, $i) === $seedRoot)->values();

        return $matched->sortBy(fn ($d) => (int) $this->get($d, 'time_ms'))->values();
    }

    /**
     * Count how many of the three signals (name/colored/plain) actually match
     * between two demos. Higher count = higher confidence. Returns 0..3.
     */
    public function signalStrength($a, $b): int
    {
        $count = 0;
        if ($this->eq($this->normName($this->get($a, 'player_name')), $this->normName($this->get($b, 'player_name')))) {
            $count++;
        }
        if ($this->eq($this->normColored($this->get($a, 'q3df_login_name_colored')), $this->normColored($this->get($b, 'q3df_login_name_colored')))) {
            $count++;
        }
        if ($this->eq($this->normPlain($this->get($a, 'q3df_login_name')), $this->normPlain($this->get($b, 'q3df_login_name')))) {
            $count++;
        }
        return $count;
    }

    private function eq(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && $a === $b;
    }

    private function demoKey($demo): ?string
    {
        $id = $this->get($demo, 'id');
        if ($id === null) {
            return null;
        }
        return 'd:' . $id;
    }

    private function get($demo, string $key)
    {
        if (is_array($demo)) {
            return $demo[$key] ?? null;
        }
        return $demo->{$key} ?? null;
    }

    private function normName(?string $value): ?string
    {
        if (!$value) return null;
        $stripped = preg_replace('/\^[0-9\[\]]/', '', $value);
        $stripped = trim(strtolower($stripped));
        return $stripped !== '' ? $stripped : null;
    }

    private function normColored(?string $value): ?string
    {
        if (!$value) return null;
        $v = trim($value);
        return $v !== '' ? $v : null;
    }

    private function normPlain(?string $value): ?string
    {
        if (!$value) return null;
        $v = strtolower(trim($value));
        return $v !== '' ? $v : null;
    }

    private function find(array &$parent, int $i): int
    {
        while ($parent[$i] !== $i) {
            $parent[$i] = $parent[$parent[$i]];
            $i = $parent[$i];
        }
        return $i;
    }

    private function union(array &$parent, int $a, int $b): void
    {
        $ra = $this->find($parent, $a);
        $rb = $this->find($parent, $b);
        if ($ra !== $rb) {
            $parent[$ra] = $rb;
        }
    }
}
