<?php

namespace App\Filament\Forms;

use Closure;
use Filament\Forms;

/**
 * A tab per language, under the English article that is the source.
 *
 * The resource says what a translation of its article looks like, once, and
 * gets one tab per language in `config/locales.php`:
 *
 *     TranslationTabs::make(fn (string $locale) => [
 *         Forms\Components\TextInput::make(TranslationTabs::field($locale, 'title')),
 *         TiptapEditor::make(TranslationTabs::field($locale, 'content')),
 *     ])
 *
 * Adding a language to `config/locales.php` therefore adds its tab to every
 * article in the admin, with no further edit anywhere - the same property the
 * language files have, where a new language is a file rather than a change to
 * the site.
 *
 * The tabs are empty until somebody fills them in, and an empty tab is not
 * stored, so an article translated into one language out of five is a normal
 * state and not a half-broken one.
 */
class TranslationTabs
{
    public const STATE_KEY = 'translations';

    /** The form field name for one language's copy of one field. */
    public static function field(string $locale, string $name): string
    {
        return self::STATE_KEY . ".{$locale}.{$name}";
    }

    /** Every language the site can be read in, except the source. */
    public static function locales(): array
    {
        return collect(config('locales.supported', []))
            ->except('en')
            ->all();
    }

    public static function make(Closure $fields, string $label = 'Translations'): Forms\Components\Tabs
    {
        $tabs = [];

        foreach (self::locales() as $locale => $name) {
            $tabs[] = Forms\Components\Tabs\Tab::make($name)
                ->badge(fn ($record) => $record?->translations
                    ?->where('locale', $locale)
                    ->filter(fn ($t) => filled($t->value))
                    ->count() ?: null)
                ->schema($fields($locale));
        }

        return Forms\Components\Tabs::make($label)
            ->tabs($tabs)
            ->columnSpanFull()
            ->visible(fn () => $tabs !== []);
    }
}
