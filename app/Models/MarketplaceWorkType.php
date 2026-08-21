<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MarketplaceWorkType extends Model
{
    /**
     * One kind of work a listing can be about. Four of these ship with the
     * site; the rest are suggested by whoever needed them and approved in the
     * admin.
     */
    protected $fillable = [
        'slug',
        'label',
        'label_plural',
        'description',
        'translations',
        'color',
        'status',
        'is_core',
        'sort_order',
        'suggested_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'translations' => 'array',
        'is_core' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public const CACHE_KEY = 'marketplace:work_types';

    /**
     * Tailwind only keeps class names it can read as literals, so a colour is
     * stored as a key here and turned into classes in resources/js/utils/workTypes.js.
     * Both lists must hold the same keys.
     */
    public const COLORS = [
        'emerald', 'purple', 'orange', 'cyan', 'blue',
        'pink', 'amber', 'teal', 'rose', 'indigo', 'lime', 'gray',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(static::CACHE_KEY));
        static::deleted(fn () => Cache::forget(static::CACHE_KEY));
    }

    public function suggestedBy()
    {
        return $this->belongsTo(User::class, 'suggested_by_user_id');
    }

    public function listings()
    {
        return $this->hasMany(MarketplaceListing::class, 'work_type', 'slug');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Every type, keyed by slug, as models. Cached because a listing badge
     * asks for one on every row and eager loading a belongsTo on a string
     * key everywhere it is rendered is easy to forget.
     */
    public static function lookup(): \Illuminate\Support\Collection
    {
        return Cache::rememberForever(
            static::CACHE_KEY,
            fn () => static::orderBy('sort_order')->orderBy('label')->get()->keyBy('slug')
        );
    }

    public static function find_cached(?string $slug): ?self
    {
        return $slug ? static::lookup()->get($slug) : null;
    }

    /** One field in the visitor's language, falling back to English. */
    public function localized(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();

        $value = $this->translations[$locale][$field] ?? null;

        if ($value === null || $value === '') {
            $value = $this->{$field};
        }

        // label_plural is optional everywhere: an admin who leaves it empty
        // gets the singular rather than a blank badge.
        if (($value === null || $value === '') && $field === 'label_plural') {
            return $this->localized('label', $locale);
        }

        return $value;
    }

    /** What the frontend needs to draw a badge or a picker row. */
    public function toOption(?string $locale = null): array
    {
        return [
            'value' => $this->slug,
            'label' => $this->localized('label', $locale),
            'plural' => $this->localized('label_plural', $locale),
            'desc' => $this->localized('description', $locale),
            'color' => $this->color,
            'pending' => $this->status === 'pending',
        ];
    }

    /** The picker and the filters only ever offer approved types. */
    public static function options(?string $locale = null): array
    {
        return static::lookup()
            ->filter(fn (self $t) => $t->status === 'approved')
            ->map(fn (self $t) => $t->toOption($locale))
            ->values()
            ->all();
    }

    /**
     * The whole job of translating one type, written out so it can be handed
     * to somebody (or something) that will do it, and handed back as one
     * paste. Nine language tabs clicked through by hand is the thing this
     * exists to avoid.
     *
     * It names what is already there and asks for it to be left alone: the
     * author's own language is the one written by somebody who actually
     * needed the words.
     */
    public function translationPrompt(): string
    {
        $locales = collect(config('locales.supported'))->except('en');

        $done = $locales->keys()
            ->filter(fn (string $locale) => ! empty($this->translations[$locale]['label'] ?? null))
            ->values();

        $missing = $locales->keys()->diff($done)->values();

        $lines = [
            'Přelož druh práce pro marketplace na defrag.racing do všech jazyků webu.',
            '',
            'slug: ' . $this->slug,
            '',
            'Anglicky (to je zdroj, ten se nepřekládá, jen se z něj vychází):',
            '  label ......... ' . $this->label,
            '  label_plural .. ' . ($this->label_plural ?: '(prázdné, použij label)'),
            '  description ... ' . ($this->description ?: '(prázdné)'),
            '',
            'Chybí: ' . ($missing->isEmpty() ? '(nic, všechno je hotové)' : $missing->implode(', ')),
        ];

        if ($done->isNotEmpty()) {
            $lines[] = 'Už hotové, NEPŘEPISUJ:';

            foreach ($done as $locale) {
                $lines[] = '  ' . $locale . ' = ' . $this->translations[$locale]['label'];
            }
        }

        $lines = array_merge($lines, [
            '',
            'Co je co:',
            '- label = jak se ten druh práce jmenuje u jednoho inzerátu',
            '- label_plural = jak se jmenuje jako specializace tvůrce, množné číslo',
            '- description = jedna krátká věta pod názvem v pickeru, může být prázdná',
            '',
            'Pravidla:',
            '- Drž se stylu, jaký už mají ostatní druhy práce v lang/*.json na tomhle webu.',
            '- Technické názvy nech anglicky, když jim tak komunita říká.',
            '- Nepřekládej doslova, když to v tom jazyce zní blbě.',
            '',
            'Vrať mi dvě věci:',
            '1) JSON blob k vložení do Filamentu, tlačítko "Paste translations"',
            '2) hotový příkaz na produkci, přesně v tomhle tvaru:',
            '',
            '   cd /var/www/defrag-racing-project/production/current && php artisan marketplace:work-type-translations ' . $this->slug . " <<'JSON'",
            '   { ... }',
            '   JSON',
            '',
            'Formát JSON:',
            '{"cs": {"label": "...", "label_plural": "...", "description": "..."}, "de": {"label": "..."}}',
        ]);

        return implode("\n", $lines);
    }

    /**
     * Read a blob of translations. Used by the paste box in the admin and by
     * the artisan command, so the two cannot disagree about what is accepted.
     *
     * Returns the usable languages and the ones it threw away, rather than
     * failing on the first odd key: an answer that covers eight languages and
     * misspells the ninth is worth taking.
     */
    public static function parseTranslations(string $json): array
    {
        $incoming = json_decode(trim($json), true);

        if (! is_array($incoming)) {
            return ['clean' => [], 'skipped' => ['(not valid JSON: ' . json_last_error_msg() . ')']];
        }

        $supported = array_keys(config('locales.supported'));
        $clean = [];
        $skipped = [];

        foreach ($incoming as $locale => $values) {
            // English is the source and lives in the row's own columns. A copy
            // of it in here would be a second version that drifts.
            if ($locale === 'en' || ! in_array($locale, $supported, true) || ! is_array($values)) {
                $skipped[] = (string) $locale;
                continue;
            }

            $row = [];

            foreach (['label', 'label_plural', 'description'] as $field) {
                $value = trim((string) ($values[$field] ?? ''));

                if ($value !== '') {
                    $row[$field] = $value;
                }
            }

            if ($row) {
                $clean[$locale] = $row;
            } else {
                $skipped[] = (string) $locale;
            }
        }

        return ['clean' => $clean, 'skipped' => $skipped];
    }

    /**
     * Merge a blob of translations onto this type. Returns how many languages
     * it now reads in, or null if there was nothing usable.
     *
     * Merging is the default because whoever suggested the type may have
     * written their own language in already, and that is the one language
     * written by somebody who actually needed the words.
     */
    public function applyTranslations(string $json, bool $replace = false): ?int
    {
        $clean = static::parseTranslations($json)['clean'];

        if (! $clean) {
            return null;
        }

        $existing = $this->translations ?? [];

        $this->translations = $replace
            ? $clean
            : collect($existing)
                ->mapWithKeys(fn ($row, $locale) => [$locale => array_merge((array) $row, $clean[$locale] ?? [])])
                ->union($clean)
                ->all();

        $this->save();

        return count($this->translations);
    }

    /**
     * Turn what a user typed into a slug nobody is using yet.
     * "Sound design" -> sound_design, then sound_design_2 if that is taken.
     */
    public static function slugFor(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'work_type';
        $base = Str::limit($base, 40, '');
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '_' . $i++;
        }

        return $slug;
    }
}
