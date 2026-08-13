<?php

namespace App\Filament\Concerns;

use App\Filament\Forms\TranslationTabs;

/**
 * Load and store what the language tabs hold, on a Filament create or edit
 * page.
 *
 * The translations are not columns on the record, so they have to be lifted
 * out of the form data before the model is saved - Eloquent would otherwise
 * try to write a `translations` attribute that does not exist - and written
 * back afterwards, when a newly created record finally has an id.
 */
trait SavesTranslations
{
    protected array $pendingTranslations = [];

    /** Edit: fill the tabs from what is stored. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data[TranslationTabs::STATE_KEY] = $this->record?->translationMatrix() ?? [];

        return $data;
    }

    /** Edit: take the tabs out of the way of the model save. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->liftTranslations($data);
    }

    /** Create: same, before there is a record at all. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->liftTranslations($data);
    }

    protected function afterSave(): void
    {
        $this->storeTranslations();
    }

    protected function afterCreate(): void
    {
        $this->storeTranslations();
    }

    private function liftTranslations(array $data): array
    {
        $this->pendingTranslations = $data[TranslationTabs::STATE_KEY] ?? [];

        unset($data[TranslationTabs::STATE_KEY]);

        return $data;
    }

    private function storeTranslations(): void
    {
        foreach ($this->pendingTranslations as $locale => $values) {
            if (is_array($values)) {
                $this->record->setTranslations($locale, $values);
            }
        }

        $this->pendingTranslations = [];
    }
}
