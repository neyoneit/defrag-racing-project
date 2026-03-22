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
