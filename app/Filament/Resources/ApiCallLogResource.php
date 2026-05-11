<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiCallLogResource\Pages;
use App\Models\ApiCallLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiCallLogResource extends Resource
{
    protected static ?string $model = ApiCallLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationGroup = 'Moderation';
    protected static ?string $navigationLabel = 'API call log';
    protected static ?int $navigationSort = 50;
    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->admin || $user->is_moderator);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ApiCallLog::where('created_at', '>=', now()->subDay())->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Resources\ApiCallLogResource\Widgets\ApiCallStatsOverview::class,
        ];
    }

    /**
     * The main list page aggregates one row per user — total calls in
     * the visible window, last activity. Clicking the user drills into
     * the per-endpoint breakdown for that user.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ApiCallLog::query()
                ->selectRaw('user_id, COUNT(*) as call_count, MAX(created_at) as last_call, SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as error_count, SUM(CASE WHEN token_id IS NOT NULL THEN 1 ELSE 0 END) as token_call_count')
                ->groupBy('user_id')
            )
            ->defaultSort('call_count', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '')
                    ->html(),
                Tables\Columns\TextColumn::make('call_count')
                    ->label('Total calls')
                    ->sortable()
                    ->numeric()
                    ->color(fn ($state) => $state > 5000 ? 'danger' : ($state > 1000 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('token_call_count')
                    ->label('Via token')
                    ->numeric()
                    ->color('info')
                    ->tooltip('Calls made with an API Bearer token (vs browser session)'),
                Tables\Columns\TextColumn::make('error_count')
                    ->label('Errors')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('last_call')
                    ->label('Last call')
                    ->dateTime('Y-m-d H:i:s')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('viewActivity')
                    ->label('Detail')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn ($record) => Pages\ViewUserActivity::getUrl(['user' => $record->user_id])),
                Tables\Actions\Action::make('viewProfile')
                    ->label('Profile')
                    ->icon('heroicon-o-user-circle')
                    ->color('gray')
                    ->url(fn ($record) => "/profile/{$record->user_id}")
                    ->openUrlInNewTab(),
            ])
            ->filters([
                Tables\Filters\Filter::make('last_24h')
                    ->label('Last 24h')
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDay())),
                Tables\Filters\Filter::make('last_7d')
                    ->label('Last 7 days')
                    ->default()
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(7))),
                Tables\Filters\Filter::make('errors_only')
                    ->label('Had errors')
                    ->query(fn ($query) => $query->havingRaw('SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) > 0')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'              => Pages\ListApiCallLogs::route('/'),
            'user-activity'      => Pages\ViewUserActivity::route('/users/{user}'),
            'endpoint-activity'  => Pages\ViewUserEndpointActivity::route('/users/{user}/endpoint'),
            'view-call'          => Pages\ViewApiCall::route('/calls/{record}'),
        ];
    }
}
