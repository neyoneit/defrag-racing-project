<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompMapReportResource\Pages;
use App\Filament\Resources\UserResource;
use App\Models\CompMapReport;
use App\Services\Comps\MapEligibilityTagger;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "This map cannot be finished in CPM / VQ3."
 *
 * Approving is not a small edit. It tags the map permanently, drops it off the
 * ballot it was reported on, and keeps it out of that physics' pool for good -
 * so it is an admin decision rather than a threshold of reports. One person
 * struggling with a map and a map being impossible look identical from here.
 */
class CompMapReportResource extends Resource
{
    protected static ?string $model = CompMapReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Comps: impossible maps';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** Open reports are the work; the badge is the queue length. */
    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::where('status', 'open')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('map.name')
                    ->label('Map')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('physics')
                    ->label('Impossible in')
                    ->badge()
                    ->color(fn (string $state) => $state === 'cpm' ? 'info' : 'warning')
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\TextColumn::make('round.comp.number')
                    ->label('Comp')
                    ->formatStateUsing(fn ($state, CompMapReport $r) => $r->round?->comp?->title),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'warning',
                        'approved' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('open'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CompMapReport $r) => $r->status === 'open')
                    ->requiresConfirmation()
                    ->modalDescription(fn (CompMapReport $r) => "Tags {$r->map?->name} as impossible in "
                        . strtoupper($r->physics)
                        . '. It leaves that ballot now and never enters that pool again.')
                    ->action(function (CompMapReport $r) {
                        app(MapEligibilityTagger::class)->approve($r, auth()->id());

                        Notification::make()
                            ->title('Tagged and removed from that ballot')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (CompMapReport $r) => $r->status === 'open')
                    ->requiresConfirmation()
                    ->action(function (CompMapReport $r) {
                        $r->update(['status' => 'rejected']);

                        Notification::make()->title('Rejected')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No reports')
            ->emptyStateDescription('Nobody has reported a candidate map as impossible.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['map', 'reporter', 'round.comp']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompMapReports::route('/'),
        ];
    }
}
