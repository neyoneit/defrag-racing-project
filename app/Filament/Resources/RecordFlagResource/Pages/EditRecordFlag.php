<?php

namespace App\Filament\Resources\RecordFlagResource\Pages;

use App\Filament\Resources\RecordFlagResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecordFlag extends EditRecord
{
    protected static string $resource = RecordFlagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['admin_notes'])) {
            $adminName = auth()->user()->name;
            $timestamp = now()->format('Y-m-d H:i');
            // Only prefix if not already prefixed
            if (!str_starts_with($data['admin_notes'], '[')) {
                $data['admin_notes'] = "[{$adminName} - {$timestamp}] {$data['admin_notes']}";
            }
            $data['resolved_by_admin_id'] = auth()->id();
            $data['resolved_at'] = $data['resolved_at'] ?? now();
        }

        return $data;
    }
}
