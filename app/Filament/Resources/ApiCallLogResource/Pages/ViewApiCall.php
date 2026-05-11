<?php

namespace App\Filament\Resources\ApiCallLogResource\Pages;

use App\Filament\Resources\ApiCallLogResource;
use App\Models\ApiCallLog;
use Filament\Resources\Pages\Page;

class ViewApiCall extends Page
{
    protected static string $resource = ApiCallLogResource::class;
    protected static string $view = 'filament.api-call-log.view-call';

    public ?ApiCallLog $call = null;

    public function mount(int $record): void
    {
        $this->call = ApiCallLog::with('user', 'token')->findOrFail($record);
    }

    public function getTitle(): string
    {
        return "API call #{$this->call->id}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            ApiCallLogResource::getUrl()                                                   => 'API call log',
            ApiCallLogResource::getUrl('user-activity', ['user' => $this->call->user_id]) => $this->call->user?->plain_name ?? "user #{$this->call->user_id}",
            '#'                                                                            => "call #{$this->call->id}",
        ];
    }
}
