<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAlias;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NameMatcher
{
    /**
     * Cached user data to avoid N+1 queries
     * Format: ['user_id' => ['plain_name' => ..., 'aliases' => [...]]]
     */
    protected ?array $userCache = null;

    /**
     * Strip Quake 3 color codes from a name
     * Removes patterns like ^0-^9, ^[, ^]
     */
    public function stripColorCodes(string $name): string
    {
        // Remove Quake 3 color codes: ^0 through ^9, ^[, ^]
        $pattern = '/\^[0-9\[\]]/';
        $stripped = preg_replace($pattern, '', $name);

        // Trim whitespace
        return trim($stripped);
    }

    /**
     * Load all users and their aliases into memory cache
     * This eliminates N+1 queries when matching multiple demos
     */
    protected function loadUserCache(): void
    {
        if ($this->userCache !== null) {
            return; // Already loaded
        }

        // Try to get from cache first (5 minute cache)
        $this->userCache = Cache::remember('name_matcher_users', 300, function () {
            $cache = [];

            // Load all users with their aliases in a single query with eager loading
            $users = User::with(['aliases' => function ($query) {
                $query->where('is_approved', true);
            }])->get();

            foreach ($users as $user) {
                $cache[$user->id] = [
                    'plain_name' => $user->plain_name,
                    'aliases' => $user->aliases->pluck('alias')->toArray(),
                ];
            }

            return $cache;
        });
    }

    /**
     * Clear the user cache (useful when users or aliases are updated)
     */
    public function clearCache(): void
    {
        $this->userCache = null;
        Cache::forget('name_matcher_users');
    }

    /**
     * Calculate similarity confidence between two names (0-100%)
     * Uses Levenshtein distance for fuzzy matching
     */
    public function calculateConfidence(string $name1, string $name2): int
    {
        // Strip color codes from both names
        $clean1 = $this->stripColorCodes($name1);
        $clean2 = $this->stripColorCodes($name2);

        // Case-insensitive comparison
        $clean1 = strtolower($clean1);
        $clean2 = strtolower($clean2);

        // Exact match = 100%
        if ($clean1 === $clean2) {
            return 100;
        }

        // Empty strings = 0%
        if (empty($clean1) || empty($clean2)) {
            return 0;
        }

        // Calculate Levenshtein distance
        $distance = levenshtein($clean1, $clean2);
        $maxLength = max(strlen($clean1), strlen($clean2));

        // Convert distance to similarity percentage
        // Lower distance = higher similarity
        $similarity = (1 - ($distance / $maxLength)) * 100;

        // Round to integer and clamp between 0-100
        return max(0, min(100, (int) round($similarity)));
    }

    /**
     * Two-pass matching: priority (uploader) then global
     *
     * @param string $demoPlayerName Name from the demo file
     * @param int|null $uploaderId ID of user who uploaded the demo
     * @return array{user_id: int|null, confidence: int, source: string, matched_name: string|null}
     */
    public function findBestMatch(string $demoPlayerName, ?int $uploaderId = null): array
    {
        // Pass 1: Check global search first
        $globalMatch = $this->matchGlobally($demoPlayerName, null); // Don't exclude uploader

        // If we found a 100% confidence match globally, always use it
        if ($globalMatch['confidence'] === 100) {
            return $globalMatch;
        }

        // Pass 2: Check uploader's primary name and aliases (if uploader is logged in)
        if ($uploaderId) {
            $uploaderMatch = $this->matchAgainstUser($demoPlayerName, $uploaderId);

            // If uploader has a high confidence match (>=70%) and it's better than global, use it
            if ($uploaderMatch['confidence'] >= 70 && $uploaderMatch['confidence'] > $globalMatch['confidence']) {
                return [
                    'user_id' => $uploaderId,
                    'confidence' => $uploaderMatch['confidence'],
                    'source' => 'uploader',
                    'matched_name' => $uploaderMatch['matched_name'],
                ];
            }
        }

        // Return the global match
        return $globalMatch;
    }

    /**
     * Match demo name against a specific user's name and aliases
     * Uses cached data instead of database queries
     *
     * @param string $demoPlayerName Name from demo
     * @param int $userId User ID to check against
     * @return array{confidence: int, matched_name: string|null}
     */
    public function matchAgainstUser(string $demoPlayerName, int $userId): array
    {
        // Load cache if not already loaded
        $this->loadUserCache();

        // Check if user exists in cache
        if (!isset($this->userCache[$userId])) {
            return ['confidence' => 0, 'matched_name' => null];
        }

        $userData = $this->userCache[$userId];
        $bestConfidence = 0;
        $matchedName = null;

        // Check against plain_name
        if (!empty($userData['plain_name'])) {
            $confidence = $this->calculateConfidence($demoPlayerName, $userData['plain_name']);
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $matchedName = $userData['plain_name'];
            }
        }

        // Check against user's approved aliases
        foreach ($userData['aliases'] as $alias) {
            $confidence = $this->calculateConfidence($demoPlayerName, $alias);
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $matchedName = $alias;
            }
        }

        return [
            'confidence' => $bestConfidence,
            'matched_name' => $matchedName,
        ];
    }

    /**
     * Match demo name globally across all users
     * Uses cached data instead of loading all users from database
     *
     * @param string $demoPlayerName Name from demo
     * @param int|null $excludeUserId User ID to exclude from search (already checked)
     * @return array{user_id: int|null, confidence: int, source: string, matched_name: string|null}
     */
    public function matchGlobally(string $demoPlayerName, ?int $excludeUserId = null): array
    {
        // Load cache if not already loaded
        $this->loadUserCache();

        $bestMatch = [
            'user_id' => null,
            'confidence' => 0,
            'source' => 'global',
            'matched_name' => null,
        ];

        // Iterate through cached users instead of database query
        foreach ($this->userCache as $userId => $userData) {
            // Skip excluded user
            if ($userId === $excludeUserId) {
                continue;
            }

            $match = $this->matchAgainstUser($demoPlayerName, $userId);

            if ($match['confidence'] > $bestMatch['confidence']) {
                $bestMatch['user_id'] = $userId;
                $bestMatch['confidence'] = $match['confidence'];
                $bestMatch['matched_name'] = $match['matched_name'];
            }

            // Early exit if we found a perfect match
            if ($bestMatch['confidence'] === 100) {
                break;
            }
        }

        return $bestMatch;
    }

    /**
     * Get all potential matches above a minimum confidence threshold
     * Useful for admin review interfaces
     * Uses cached data instead of loading all users from database
     *
     * @param string $demoPlayerName Name from demo
     * @param int $minConfidence Minimum confidence threshold (default 50)
     * @return Collection Array of matches with user_id, confidence, matched_name
     */
    public function getAllMatches(string $demoPlayerName, int $minConfidence = 50): Collection
    {
        // Load cache if not already loaded
        $this->loadUserCache();

        $matches = collect();

        foreach ($this->userCache as $userId => $userData) {
            $match = $this->matchAgainstUser($demoPlayerName, $userId);

            if ($match['confidence'] >= $minConfidence) {
                $matches->push([
                    'user_id' => $userId,
                    'user_name' => $userData['plain_name'],
                    'confidence' => $match['confidence'],
                    'matched_name' => $match['matched_name'],
                ]);
            }
        }

        // Sort by confidence descending
        return $matches->sortByDesc('confidence')->values();
    }
}
