<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompDemoReportResource\Pages;
use App\Filament\Resources\UserResource;
use App\Models\CompDemoReport;
use App\Services\Comps\ResultsCalculator;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reports about comps demos, of two kinds.
 *
 * `entry` is an accusation: that run is not what it claims to be. Upholding one
 * drops the entry out of comps and rebuilds the standings without it. The demo
 * itself stays in the demo database - being wrong for a competition is not a
 * reason to erase somebody's file, and the demo is often the only evidence of
 * what happened.
 *
 * `help` is somebody asking about a demo of their own that comps did not take:
 * a file the parser could not read, a run held for a map still being voted on.
 * There is nothing to uphold - no entry exists - so it is read, answered and
 * closed. It is the commoner of the two and the reason this queue is worth
 * watching.
 */
class CompDemoReportResource extends Resource
{
    protected static ?string $model = CompDemoReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Comps: demo reports';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

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
                Tables\Columns\TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === CompDemoReport::HELP ? 'Needs a look' : 'Reported run')
                    ->color(fn ($state) => $state === CompDemoReport::HELP ? 'info' : 'warning'),

                // A help report has no entry, so the name comes off the demo.
                Tables\Columns\TextColumn::make('runner')
                    ->label('Run by')
                    ->weight('bold')
                    ->getStateUsing(fn (CompDemoReport $r) => $r->submission?->user?->name ?? $r->demo?->user?->name ?? '-')
                    ->formatStateUsing(fn (?string $state): string => $state && $state !== '-' ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\TextColumn::make('demo.original_filename')
                    ->label('Demo')
                    ->limit(40)
                    ->tooltip(fn (CompDemoReport $r) => $r->demo?->original_filename)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('submission.time')
                    ->label('Time')
                    ->formatStateUsing(fn ($state) => $state ? gmdate('i:s', intdiv($state, 1000)) . '.' . str_pad($state % 1000, 3, '0', STR_PAD_LEFT) : '-'),

                Tables\Columns\TextColumn::make('submission.physics')
                    ->label('Physics')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state)),

                Tables\Columns\TextColumn::make('submission.status')
                    ->label('Entry')
                    ->badge()
                    ->color(fn ($state) => $state === 'valid' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\TextColumn::make('reason')
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn (CompDemoReport $r) => $r->reason),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'warning',
                        'upheld' => 'danger',
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
                        'upheld' => 'Upheld',
                        'dismissed' => 'Dismissed',
                    ])
                    ->default('open'),

                Tables\Filters\SelectFilter::make('kind')
                    ->options([
                        CompDemoReport::HELP => 'Needs a look',
                        CompDemoReport::ENTRY => 'Reported run',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Demo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (CompDemoReport $r) => (bool) static::demoId($r))
                    ->url(fn (CompDemoReport $r) => route('demos.download', static::demoId($r)), true),

                // Only an entry can be upheld: a help report has nothing to
                // remove, and the answer to it is a message, not a verdict.
                Tables\Actions\Action::make('uphold')
                    ->label('Uphold')
                    ->icon('heroicon-o-check')
                    ->color('danger')
                    ->visible(fn (CompDemoReport $r) => $r->status === 'open' && $r->submission !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Drops the entry from comps and rebuilds the standings without it. The demo stays in the demo database.')
                    ->action(function (CompDemoReport $r) {
                        $submission = $r->submission;

                        $submission?->update([
                            'status' => 'invalid',
                            'invalid_reason' => 'Removed after a report.',
                            // Stamped so the round page can tell this apart
                            // from a run the validator never accepted, and
                            // keep the player visible as removed.
                            'removed_by' => auth()->id(),
                            'removed_at' => now(),
                        ]);

                        $r->update([
                            'status' => 'upheld',
                            'resolved_by' => auth()->id(),
                            'resolved_at' => now(),
                        ]);

                        // Only a finished round has standings to rebuild; a
                        // running one is scored when it closes anyway.
                        $round = $submission?->round;

                        if ($round && $round->status === 'finished') {
                            app(ResultsCalculator::class)->freeze($round);
                        }

                        Notification::make()->title('Entry removed, standings rebuilt')->success()->send();
                    }),

                Tables\Actions\Action::make('dismiss')
                    ->label(fn (CompDemoReport $r) => $r->kind === CompDemoReport::HELP ? 'Answered' : 'Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (CompDemoReport $r) => $r->status === 'open')
                    ->requiresConfirmation()
                    ->action(function (CompDemoReport $r) {
                        $r->update([
                            'status' => 'dismissed',
                            'resolved_by' => auth()->id(),
                            'resolved_at' => now(),
                        ]);

                        Notification::make()->title('Dismissed')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No reports')
            ->emptyStateDescription('Nobody has reported a comps run.');
    }

    /** The demo to open: the report's own, or the entry's if it has none. */
    private static function demoId(CompDemoReport $report): ?int
    {
        return $report->uploaded_demo_id ?? $report->submission?->uploaded_demo_id;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['submission.user', 'submission.round', 'demo.user', 'reporter']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompDemoReports::route('/'),
        ];
    }
}
