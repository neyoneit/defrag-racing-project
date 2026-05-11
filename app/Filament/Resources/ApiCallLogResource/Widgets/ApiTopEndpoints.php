<?php

namespace App\Filament\Resources\ApiCallLogResource\Widgets;

use App\Models\ApiCallLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ApiTopEndpoints extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Top endpoints (last 7 days)';
    protected int | string | array $columnSpan = 1;

    public function getTableRecordKey($record): string
    {
        return (string) ($record->route ?? '0');
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->query(function (): Builder {
                return ApiCallLog::query()
                    ->selectRaw('route, COUNT(*) as call_count, AVG(response_ms) as avg_ms')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('route')
                    ->orderByDesc('call_count')
                    ->limit(10);
            })
            ->columns([
                Tables\Columns\TextColumn::make('route'),
                Tables\Columns\TextColumn::make('call_count')
                    ->label('Calls')
                    ->numeric()
                    ->color(fn ($state) => $state > 5000 ? 'danger' : ($state > 1000 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('avg_ms')
                    ->label('Avg ms')
                    ->formatStateUsing(fn ($state) => $state !== null ? round((float) $state) : '—')
                    ->color(fn ($state) => $state !== null && (float) $state > 1000 ? 'warning' : 'gray'),
            ])
            ->recordUrl(fn ($record) => "/defraghq/api-call-logs?tableFilters[route][value]=" . urlencode($record->route));
    }
}
