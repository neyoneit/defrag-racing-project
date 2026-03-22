<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BotVisitResource\Pages;
use App\Models\BotVisit;
use App\Services\BotDetector;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BotVisitResource extends Resource
{
    protected static ?string $model = BotVisit::class;

    protected static ?string $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $navigationLabel = 'Bot Tracker';

    protected static ?string $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('hits', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'GET' => 'info',
                        'POST' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('path')
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->path),
                Tables\Columns\TextColumn::make('hits')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state >= 100 => 'danger',
                        $state >= 20 => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 400 => 'danger',
                        $state >= 300 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->user_agent),
                Tables\Columns\TextColumn::make('is_verified')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => BotDetector::isVerifiedBot($record->user_agent) ? 'Verified' : 'Fake/Unknown')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Verified' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last seen')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query) => $query->where('date', now()->toDateString()))
                    ->default(true),
                Tables\Filters\Filter::make('last_7_days')
                    ->label('Last 7 days')
                    ->query(fn (Builder $query) => $query->where('date', '>=', now()->subDays(7)->toDateString())),
                Tables\Filters\Filter::make('fake_bots')
                    ->label('Fake bots only')
                    ->query(fn (Builder $query) => $query->whereRaw("user_agent NOT REGEXP 'Googlebot|Bingbot|Slurp|DuckDuckBot|Baiduspider|YandexBot|facebookexternalhit|Twitterbot|LinkedInBot|Discordbot'")),
                Tables\Filters\Filter::make('high_hits')
                    ->label('High activity (20+ hits)')
                    ->query(fn (Builder $query) => $query->where('hits', '>=', 20)),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBotVisits::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('date', now()->toDateString())->sum('hits') ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $hits = static::getModel()::where('date', now()->toDateString())->sum('hits');
        return $hits >= 100 ? 'danger' : 'warning';
    }
}
