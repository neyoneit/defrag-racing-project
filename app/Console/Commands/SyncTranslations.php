<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class SyncTranslations extends Command
{
    protected $signature = 'lang:sync
                            {--prune : Also drop lines whose English sentence is no longer used anywhere}';

    protected $description = 'Collect every translatable string and top up each lang/<locale>.json with the ones it is missing';

    /**
     * The point of this command is that a language file can be handed to
     * somebody who does not know the codebase. It scans for translatable
     * strings, adds the new ones with an empty value, and leaves everything
     * already translated alone - so a translator reopens the file and sees
     * exactly what has appeared since last time as the blanks.
     *
     * Empty is deliberately not the English text: a file pre-filled with
     * English is indistinguishable from a finished one, and nobody can tell
     * what still needs doing.
     */
    public function handle(): int
    {
        $keys = $this->collect();

        if ($keys === []) {
            $this->warn('Found no translatable strings at all - has the scan path moved?');

            return self::FAILURE;
        }

        $this->info(count($keys) . ' translatable string(s) in the codebase.');

        // English is the source. Its strings live in the templates, so it has
        // no file and must never get one.
        $locales = array_diff(array_keys(config('locales.supported')), ['en']);

        foreach ($locales as $locale) {
            $this->sync($locale, $keys);
        }

        return self::SUCCESS;
    }

    /**
     * The $t and $tc helpers in Vue, __ and @lang in PHP and Blade. Only
     * literal single- or double-quoted first arguments - a key built at
     * runtime cannot be extracted, and translating one means writing its
     * possible values out as literals somewhere.
     *
     * The scan does not know code from comment, so writing one of these calls
     * out in prose anywhere puts its argument in every language file. Describe
     * them, do not spell them.
     */
    private function collect(): array
    {
        $patterns = [
            '/(?<![\w$])\$?t(?:c|Choice)?\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/',
            '/(?<![\w$>])__\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/',
            '/@lang\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/',
        ];

        $finder = (new Finder())
            ->files()
            ->in([base_path('resources/js'), base_path('resources/views'), base_path('app')])
            ->name(['*.vue', '*.js', '*.php'])
            ->notPath('vendor');

        $keys = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();

            foreach ($patterns as $pattern) {
                if (! preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $key = stripcslashes($match[2]);

                    if ($key !== '') {
                        $keys[$key] = true;
                    }
                }
            }
        }

        $keys = array_keys($keys);

        // Alphabetical, case-insensitive: the file is read and edited by
        // hand, and a stable order keeps a diff to the lines that changed.
        usort($keys, 'strcasecmp');

        return $keys;
    }

    private function sync(string $locale, array $keys): void
    {
        $path = base_path("lang/{$locale}.json");

        $existing = file_exists($path)
            ? (json_decode(file_get_contents($path), true) ?: [])
            : [];

        $merged = [];
        $added = 0;

        foreach ($keys as $key) {
            $merged[$key] = $existing[$key] ?? '';

            if (! array_key_exists($key, $existing)) {
                $added++;
            }
        }

        $orphans = array_diff(array_keys($existing), $keys);

        // An orphan is usually an English sentence somebody has since edited,
        // and its translation is the only copy of that work - keep it unless
        // asked, so a reworded button does not quietly throw away a line
        // somebody wrote.
        if (! $this->option('prune')) {
            foreach ($orphans as $orphan) {
                $merged[$orphan] = $existing[$orphan];
            }

            uksort($merged, 'strcasecmp');
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $done = count(array_filter($merged, fn ($line) => $line !== ''));
        $percent = count($keys) > 0 ? round($done / count($keys) * 100) : 0;

        $this->line(sprintf(
            '  %s: %d translated of %d (%d%%), %d new%s',
            $locale,
            $done,
            count($keys),
            $percent,
            $added,
            $orphans === []
                ? ''
                : ', ' . count($orphans) . ' no longer used' . ($this->option('prune') ? ' (removed)' : '')
        ));
    }
}
