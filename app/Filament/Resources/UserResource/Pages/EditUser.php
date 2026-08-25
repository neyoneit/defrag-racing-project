<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Put the address back for this one form.
     *
     * `email` sits in the User model's $hidden list, because a User is
     * serialized into public page payloads wherever it hangs off something
     * else and those payloads are readable by anyone. Filament fills a form
     * from the same array that list censors, so the box arrived empty on a
     * page whose whole audience is an administrator - and empty plus required
     * meant the form refused to save anything at all, address or not.
     *
     * Read off the record itself, which the $hidden list does not touch. The
     * model keeps its guard and the public payloads stay as they are.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['email'] = $this->record->email;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invalidate_sessions')
                ->label('Force Logout')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Force Logout User')
                ->modalDescription(fn () => "This will terminate all active sessions for {$this->record->plain_name}. They will be logged out immediately.")
                ->action(function () {
                    $deleted = DB::table('sessions')
                        ->where('user_id', $this->record->id)
                        ->delete();

                    Notification::make()
                        ->title("Logged out {$this->record->plain_name} ({$deleted} sessions terminated)")
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
