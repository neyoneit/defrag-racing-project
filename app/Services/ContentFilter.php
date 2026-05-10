<?php

namespace App\Services;

/**
 * Profanity / slur filter for user-controlled strings shown in YouTube
 * metadata, Discord posts, and other public-facing copy.
 *
 * Ported from DefragLive's src/filters.py to keep blocklist behaviour
 * consistent across the site, the live bot and the demo render pipeline.
 * Two main entry points:
 *   - filterAuthor()  — for nicknames; whole string is replaced with a
 *                        safe placeholder when any blocked term is detected
 *   - filterText()    — for free-form text (map names, descriptions); each
 *                        blocked term is masked with asterisks in place
 *
 * Detection mirrors the Python version's leetspeak handling: numbers and
 * a few visually similar punctuation chars are expanded to their letter
 * equivalents (3->e, 0->o, |->i, etc.) before the blocklist is checked,
 * and runs of repeated characters are collapsed so "f**aaaag**ot" still
 * trips "faggot".
 */
class ContentFilter
{
    /**
     * Letter substitutions tried when expanding leetspeak. Mirrors
     * SPECIAL_NUMBERS in DefragLive filters.py.
     */
    protected const SPECIAL_CHARS = [
        '0' => ['o'],
        '1' => ['i', 'l'],
        '2' => ['z'],
        '3' => ['e'],
        '4' => ['a', 'h'],
        '5' => ['s'],
        '6' => ['g', 'b'],
        '7' => ['t'],
        '8' => ['b', 'g'],
        '9' => ['g'],
        'l' => ['i'],
        '!' => ['i', 'l'],
        '|' => ['i', 'l'],
    ];

    /**
     * Cap on the number of leetspeak permutations explored per string.
     * Typical nicks with 1-3 substitutable chars produce 2-8 variants;
     * the limit only kicks in for pathological inputs (long strings made
     * entirely of digits/punctuation) where the combinatorial blow-up
     * would be wasted CPU.
     */
    protected const MAX_VARIANTS = 64;

    /** @var array<int, string>|null cached blocklist */
    protected static ?array $blocklist = null;

    /**
     * Strip Quake 3 colour codes (^0..^9, ^a..^z, plus the ^Xrrggbb hex
     * form used by some clients).
     */
    public static function stripQ3Colors(string $value): string
    {
        return preg_replace('/\^([Xx][0-9A-Fa-f]{6}|[0-9a-zA-Z])/', '', $value);
    }

    /**
     * For nicknames: returns the placeholder when the input contains any
     * blocked term (after leetspeak expansion + repeated-char collapse),
     * otherwise returns the input with colour codes stripped.
     */
    public static function filterAuthor(?string $author, string $replaceWith = 'UnnamedPlayer'): string
    {
        if ($author === null || $author === '') {
            return $replaceWith;
        }
        $stripped = self::cleanString($author);
        $lower = strtolower($stripped);
        if (self::matchesBlocklist($lower)) {
            return $replaceWith;
        }
        return self::stripQ3Colors($author);
    }

    /**
     * For free-form text (map names, descriptions): masks every blocked
     * term with asterisks in place. Returns the input with colour codes
     * stripped when no blocked term is found.
     */
    public static function filterText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $clean = self::stripQ3Colors($text);
        // Word-START match: blocked term must begin at a non-letter
        // boundary (start of string, hyphen, underscore, digit, etc.) but
        // may be followed by any letter suffix. Catches "atz-oh_fuck",
        // "fucking-nice", "drugs_map", etc. without false-positives like
        // "rape" inside "skyscraper" or "cock" inside "peacock".
        // One blocklist entry covers every English conjugation: "fuck"
        // matches fucker/fucking/fucked, "rape" matches raped/rapist.
        foreach (self::blocklist() as $word) {
            $clean = preg_replace_callback(
                '/(?<![a-zA-Z])' . preg_quote($word, '/') . '[a-zA-Z]*/i',
                fn ($m) => str_repeat('*', strlen($m[0])),
                $clean
            );
        }
        return $clean;
    }

    /**
     * Return true when the (already-cleaned, lowercase) input contains any
     * blocked term. Tries leetspeak permutations so "n1gg3r" and "n!gg3r"
     * still trip "nigger".
     */
    protected static function matchesBlocklist(string $msgLower): bool
    {
        // Collapse blocklist entries on-the-fly so "fagggot" (input
        // collapsed to "fagot") matches "faggot" (blocklist collapsed
        // to "fagot"). Mirrors DefragLive filters.py behaviour.
        $collapsedBlocklist = array_map(
            fn ($w) => self::stripRepeated($w),
            self::blocklist()
        );
        $variants = self::expandSpecialChars(str_replace(' ', '', $msgLower));
        foreach ($variants as $variant) {
            $collapsed = self::stripRepeated($variant);
            foreach ($collapsedBlocklist as $word) {
                if (str_contains($collapsed, $word)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Lazily load the blocklist from resources/blacklisted_words.txt so
     * the file can be edited without redeploying.
     */
    protected static function blocklist(): array
    {
        if (self::$blocklist !== null) {
            return self::$blocklist;
        }
        $path = resource_path('blacklisted_words.txt');
        if (!is_file($path)) {
            self::$blocklist = [];
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $words = [];
        foreach ($lines as $line) {
            $w = strtolower(trim($line));
            // Skip blank lines and #-prefixed comments so the file can be
            // documented and grouped without polluting the match list.
            if ($w === '' || str_starts_with($w, '#')) {
                continue;
            }
            // Keep the entry in its native form. Repeated-character
            // collapse is applied only to the *input* inside
            // matchesBlocklist() (for nicks), so blocklist words like
            // "xxx", "boob", "coon" stay intact instead of collapsing to
            // "x", "bob", "con" (which would catch "13box_xt", "bobbybob",
            // "concrete" as false positives in filterText).
            $words[] = $w;
        }
        self::$blocklist = array_values(array_unique($words));
        return self::$blocklist;
    }

    /**
     * Drop colour codes and any character that's not a letter, digit, or
     * one of the few punctuation chars we treat as letter-equivalents
     * (matches clean_string in filters.py).
     */
    protected static function cleanString(string $value): string
    {
        $stripped = self::stripQ3Colors($value);
        $alnum    = preg_replace('/[^a-zA-Z0-9!|: ]/', '', $stripped);
        return self::stripRepeated($alnum);
    }

    /**
     * Collapse runs of identical characters: "aabbcc" -> "abc".
     */
    protected static function stripRepeated(string $value): string
    {
        $result = '';
        $prev = null;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $c = $value[$i];
            if ($c !== $prev) {
                $result .= $c;
                $prev = $c;
            }
        }
        return $result;
    }

    /**
     * Build leetspeak permutations of $msg by replacing each substitutable
     * character with every option from SPECIAL_CHARS. Capped at
     * MAX_VARIANTS so a string of all-digits doesn't blow up.
     *
     * @return array<int, string>
     */
    protected static function expandSpecialChars(string $msg): array
    {
        $variants = [''];
        $len = strlen($msg);
        for ($i = 0; $i < $len; $i++) {
            $c = $msg[$i];
            $options = self::SPECIAL_CHARS[$c] ?? [$c];
            $next = [];
            foreach ($variants as $v) {
                foreach ($options as $o) {
                    $next[] = $v . $o;
                    if (count($next) >= self::MAX_VARIANTS) {
                        $variants = $next;
                        return $variants;
                    }
                }
            }
            $variants = $next;
        }
        return $variants;
    }
}
