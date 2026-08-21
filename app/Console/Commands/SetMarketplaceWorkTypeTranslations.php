<?php

namespace App\Console\Commands;

use App\Models\MarketplaceWorkType;
use Illuminate\Console\Command;

class SetMarketplaceWorkTypeTranslations extends Command
{
    protected $signature = 'marketplace:work-type-translations
                            {slug : The work type, as its slug is shown in the admin}
                            {json? : The translations. Left out, they are read from stdin.}
                            {--replace : Throw away what is there instead of merging on top of it}';

    protected $description = 'Fill in the translations of one marketplace work type';

    /**
     * The other half of the "Copy prompt" button in the admin: the prompt asks
     * for a JSON blob, and this puts that blob on the row without anybody
     * clicking through nine language tabs. The same job can be done by
     * pasting into "Paste translations" in the admin - this is for when the
     * answer is already in a terminal.
     *
     * Reading from stdin is why the argument is optional. A French or Czech
     * label carries apostrophes, and those turn a single-quoted shell argument
     * into a mess. A quoted heredoc does not care:
     *
     *   php artisan marketplace:work-type-translations sound_design <<'JSON'
     *   {"cs": {"label": "Zvukovy design"}}
     *   JSON
     */
    public function handle(): int
    {
        $type = MarketplaceWorkType::where('slug', $this->argument('slug'))->first();

        if (! $type) {
            $this->error('No work type with the slug "' . $this->argument('slug') . '".');
            $this->line('Known: ' . MarketplaceWorkType::orderBy('slug')->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        $raw = $this->argument('json') ?? stream_get_contents(STDIN);

        if (trim((string) $raw) === '') {
            $this->error('No JSON given, and nothing on stdin.');

            return self::FAILURE;
        }

        $parsed = MarketplaceWorkType::parseTranslations($raw);

        foreach ($parsed['skipped'] as $locale) {
            $this->warn('Ignored "' . $locale . '" - not a language the site is read in, or nothing usable in it.');
        }

        $before = $type->translations ?? [];
        $count = $type->applyTranslations($raw, $this->option('replace'));

        if ($count === null) {
            $this->error('Nothing usable in that JSON.');

            return self::FAILURE;
        }

        $this->info('"' . $type->label . '" now reads in ' . $count . ' language(s).');

        foreach ($type->translations as $locale => $row) {
            $mark = ($before[$locale] ?? null) === $row ? '  ' : '->';
            $this->line("  {$mark} {$locale}: " . ($row['label'] ?? '-'));
        }

        return self::SUCCESS;
    }
}
