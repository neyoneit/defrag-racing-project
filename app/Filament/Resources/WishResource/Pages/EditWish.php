<?php

namespace App\Filament\Resources\WishResource\Pages;

use App\Filament\Resources\WishResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWish extends EditRecord
{
    protected static string $resource = WishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * The reply box is not a column on the wish, so the form never saves it.
     * Posting it here rather than in a separate action means answering and
     * replying are the same click, which is how it actually gets used.
     *
     * Saving with the box empty posts nothing. An admin sets a status far more
     * often than they write, and a blank reply on every save would fill the
     * thread with silence and tell the author about each one.
     */
    protected function afterSave(): void
    {
        $body = trim((string) ($this->data['new_reply'] ?? ''));

        if ($body === '') {
            return;
        }

        $this->record->replies()->create([
            'user_id' => auth()->id(),
            'by_admin' => true,
            'body' => $body,
        ]);

        // Not when answering your own wish - there is nobody else to tell.
        if ($this->record->user_id !== auth()->id()) {
            $this->record->notifyAuthorAnswered();
        }

        // Or the same text is still sitting in the box after the redirect and
        // gets posted again on the next save.
        $this->data['new_reply'] = '';

        Notification::make()
            ->success()
            ->title('Reply posted')
            ->body($this->record->user_id === auth()->id()
                ? 'Your own wish, so nobody was notified.'
                : 'The author has been told their wish needs an answer.')
            ->send();
    }
}
