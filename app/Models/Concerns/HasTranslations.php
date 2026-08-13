<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Admin-written text that a visitor reads, in more than one language.
 *
 * A model says which of its columns are prose:
 *
 *     use HasTranslations;
 *     public array $translatable = ['title', 'content'];
 *
 * and then `$page->translated()` hands back a copy with those columns
 * replaced by the current language, or left as they are where no translation
 * exists. That is the same rule the rest of the site follows: **English is
 * the source and a missing translation renders English**, never a blank and
 * never a key.
 *
 * `translated()` is deliberately explicit rather than an accessor that
 * rewrites the attribute on read. Filament edits these same records, and a
 * silent accessor would load the Czech title into the English field and save
 * it back over the original the first time an admin opened the page in
 * Czech. Reading a translation and editing the source are different jobs and
 * they use different calls.
 */
trait HasTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /** The fields a model declares; an empty list makes the trait a no-op. */
    public function translatableFields(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    /**
     * One field, in one language, falling back to the source column. Handy on
     * its own; `translated()` is what pages usually want.
     */
    public function tr(string $field, ?string $locale = null): mixed
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'en' || ! in_array($field, $this->translatableFields(), true)) {
            return $this->getAttribute($field);
        }

        $value = $this->translations
            ->firstWhere(fn (Translation $t) => $t->locale === $locale && $t->field === $field)
            ?->value;

        // An empty translation is an untranslated one, exactly as in the
        // language files - a half-finished row falls through per field.
        return ($value === null || $value === '') ? $this->getAttribute($field) : $value;
    }

    /**
     * A copy of the record with its prose in the current language. A copy,
     * because the original is what an admin edits and what a save writes
     * back; this one only ever goes out to a page.
     */
    public function translated(?string $locale = null): static
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale === 'en' || $this->translatableFields() === []) {
            return $this;
        }

        $this->loadMissing('translations');

        $copy = clone $this;

        foreach ($this->translatableFields() as $field) {
            $copy->setAttribute($field, $this->tr($field, $locale));
        }

        return $copy;
    }

    /**
     * Write a language's fields, keyed field => value. A field left empty is
     * removed rather than stored blank, so "no translation" has one meaning
     * in the table and the fallback stays honest.
     */
    public function setTranslations(string $locale, array $values): void
    {
        $changed = false;

        foreach ($this->translatableFields() as $field) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $value = $values[$field];

            if ($value === null || trim(strip_tags((string) $value)) === '') {
                $changed = $this->translations()
                    ->where('locale', $locale)
                    ->where('field', $field)
                    ->delete() > 0 || $changed;

                continue;
            }

            $existing = $this->translations()
                ->where('locale', $locale)
                ->where('field', $field)
                ->first();

            if ($existing?->value === $value) {
                continue;
            }

            $this->translations()->updateOrCreate(
                ['locale' => $locale, 'field' => $field],
                ['value' => $value],
            );

            $changed = true;
        }

        $this->unsetRelation('translations');

        // A translation is not a column, so saving one fires none of the
        // model's own events - and the home page keeps its announcements in
        // a cache that only those events clear. Touching the record says
        // "this changed" in the one language the rest of the code speaks.
        if ($changed) {
            $this->touch();
        }
    }

    /** Everything stored for this record, as [locale][field] => value. */
    public function translationMatrix(): array
    {
        $out = [];

        foreach ($this->translations as $row) {
            $out[$row->locale][$row->field] = $row->value;
        }

        return $out;
    }

    /**
     * A record's translations go when the record does - nothing else can
     * reach them, and a later row reusing the id would inherit them.
     */
    protected static function bootHasTranslations(): void
    {
        static::deleting(function ($model) {
            $soft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();

            if (! $soft) {
                $model->translations()->delete();
            }
        });
    }
}
