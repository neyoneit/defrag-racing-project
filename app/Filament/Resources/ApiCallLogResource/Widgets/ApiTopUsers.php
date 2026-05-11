<?php

namespace App\Filament\Resources\ApiCallLogResource\Widgets;

use App\Filament\Resources\UserResource;
use App\Models\ApiCallLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Last 7-day "who hit the API the most" leaderboard.
 *
 * We group by user_id rather than per-token because the goal is to
 * spot users (potential scrapers); per-token detail is available in
 * the main list table via filters.
 */
class ApiTopUsers extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Top users (last 7 days)';
    protected int | string | array $columnSpan = 1;

    public function getTableRecordKey($record): string
    {
        // GROUP BY query has no real primary id — use user_id as the key.
        return (string) ($record->user_id ?? '0');
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->query(function (): Builder {
                return ApiCallLog::query()
                    ->selectRaw('user_id, COUNT(*) as call_count, MAX(created_at) as last_call')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('user_id')
                    ->orderByDesc('call_count')
                    ->limit(10);
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '')
                    ->html()
                    ->url(fn ($record) => $record->user_id ? "/profile/{$record->user_id}" : null),
                Tables\Columns\TextColumn::make('call_count')
                    ->label('Calls')
                    ->numeric()
                    ->color(fn ($state) => $state > 1000 ? 'danger' : ($state > 200 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('last_call')
                    ->label('Last call')
                    ->dateTime('Y-m-d H:i')
                    ->since(),
            ])
            ->recordUrl(fn ($record) => "/defraghq/api-call-logs?tableFilters[user_id][value]={$record->user_id}");
    }
}
