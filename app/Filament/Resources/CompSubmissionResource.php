<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompSubmissionResource\Pages;
use App\Filament\Resources\UserResource;
use App\Models\CompSubmission;
use App\Services\Comps\ResultsCalculator;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every run entered into comps, and the button that takes one out.
 *
 * The reported-runs list only reaches a run somebody complained about, which
 * leaves an admin who spots a bad time himself with nothing to press. This is
 * that: the whole field, filterable, with removal available on any row.
 *
 * Removing never touches the demo. Being wrong for a competition is not a
 * reason to erase somebody's file, and the demo is usually the only evidence
 * of what actually happened - so the file stays, is released with the rest of
 * the round's demos when it ends, and only its standing in comps is taken away.
 */
class CompSubmissionResource extends Resource
{
    protected static ?string $model = CompSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Comps: entries';

    protected static ?string $navigationGroup = 'Comps';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                // Round and category on one line. As a column plus a
                // description it was two lines tall, and that alone set the
                // height of every row in the table.
                Tables\Columns\TextColumn::make('round.comp.title')
                    ->label('Comp')
                    ->formatStateUsing(fn ($state, CompSubmission $s) => trim($state . ($s->round?->category ? ' · ' . $s->round->category : '')))
                    ->size('xs')
                    ->sortable(),

                // The nick as the player wrote it. Stored with Quake's own
                // colour codes, so `^3Flasch^1-B.I.E.R-` is the raw form and
                // needs the same rendering the rest of the admin uses.
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Player')
                    ->searchable()
                    ->weight('bold')
                    ->size('xs')
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\TextColumn::make('physics')
                    ->badge()
                    ->size('xs')
                    ->formatStateUsing(fn ($state) => $state ? strtoupper($state) : '-'),

                Tables\Columns\TextColumn::make('time')
                    ->size('xs')
                    ->formatStateUsing(fn ($state) => $state
                        ? gmdate('i:s', intdiv($state, 1000)) . '.' . str_pad($state % 1000, 3, '0', STR_PAD_LEFT)
                        : '-')
                    ->sortable(),

                // Two boolean columns of mostly-red crosses cost two column
                // widths to say nothing. One column names only what is true.
                Tables\Columns\TextColumn::make('is_highlight')
                    ->label('Flags')
                    ->size('xs')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(function (CompSubmission $s): string {
                        $flags = array_filter([
                            $s->is_highlight ? 'highlight' : null,
                            $s->is_online ? 'online' : null,
                        ]);

                        return $flags ? implode(', ', $flags) : '-';
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->size('xs')
                    ->color(fn (string $state) => match ($state) {
                        'valid' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    })
                    // An admin removal and a validator refusal are both
                    // `invalid` and want telling apart at a glance.
                    ->formatStateUsing(fn (CompSubmission $s) => $s->wasRemovedByAdmin() ? 'removed' : $s->status),

                // Not wrapped: a long refusal used to grow its row to three
                // lines. The whole sentence is in the tooltip.
                Tables\Columns\TextColumn::make('invalid_reason')
                    ->label('Reason')
                    ->size('xs')
                    ->limit(40)
                    ->tooltip(fn (CompSubmission $s) => $s->invalid_reason)
                    ->toggleable(),

                // How the run got here, which is the first thing asked when
                // somebody says they never entered: `auto` means the guard
                // took the demo in on its own, and the route is where the file
                // itself came from. `web` on a row older than 18 Aug 2026 only
                // means nothing wrote a route - see UploadedDemo::SOURCE_WEB.
                Tables\Columns\TextColumn::make('demo.source')
                    ->label('Via')
                    ->size('xs')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state, CompSubmission $s): string =>
                        ($s->auto_entered ? 'auto' : 'entered') . ' / ' . ($state ?: '?'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->size('xs')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('comp_round_id')
                    ->label('Round')
                    ->relationship('round', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->comp?->title ?? 'Round') . ' #' . $record->index),

                Tables\Filters\SelectFilter::make('physics')
                    ->options(['cpm' => 'CPM', 'vq3' => 'VQ3']),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'valid' => 'Valid',
                        'invalid' => 'Invalid',
                    ]),

                Tables\Filters\TernaryFilter::make('is_highlight')
                    ->label('Highlight'),
            ])
            // Collapsed into one menu: three buttons on every row is a
            // column of its own, and only one of them is ever pressed.
            ->actions([
                Tables\Actions\ActionGroup::make([
                Tables\Actions\Action::make('download')
                    ->label('Demo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (CompSubmission $s) => $s->uploaded_demo_id !== null)
                    ->url(fn (CompSubmission $s) => route('demos.download', $s->uploaded_demo_id), true),

                Tables\Actions\Action::make('remove')
                    ->label('Remove')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (CompSubmission $s) => $s->status === 'valid')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->helperText('Shown to the player on their entry, and in the tooltip next to their name on the round page.')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->modalDescription('Takes the run out of the standings and rebuilds them without it. The demo itself stays in the demo database and is released with the rest of the round.')
                    ->action(function (CompSubmission $s, array $data) {
                        $s->update([
                            'status' => 'invalid',
                            'invalid_reason' => $data['reason'],
                            'removed_by' => auth()->id(),
                            'removed_at' => now(),
                        ]);

                        static::rebuild($s);

                        Notification::make()->title('Run removed')->success()->send();
                    }),

                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    // Only a removal is undone here. A run the validator
                    // refused is refused for a reason that is still true, and
                    // waving it back in would put a wrong-map demo in the
                    // standings.
                    ->visible(fn (CompSubmission $s) => $s->wasRemovedByAdmin())
                    ->requiresConfirmation()
                    ->modalDescription('Puts the run back into the standings.')
                    ->action(function (CompSubmission $s) {
                        $s->update([
                            'status' => 'valid',
                            'invalid_reason' => null,
                            'removed_by' => null,
                            'removed_at' => null,
                        ]);

                        static::rebuild($s);

                        Notification::make()->title('Run restored')->success()->send();
                    }),
                ]),
            ])
            ->emptyStateHeading('No entries')
            ->emptyStateDescription('Nobody has uploaded a run to comps yet.');
    }

    /**
     * Only a finished round has frozen standings to correct. A running one is
     * scored when it closes, off whatever the entries say at that moment.
     */
    private static function rebuild(CompSubmission $submission): void
    {
        $round = $submission->round;

        if ($round && $round->status === 'finished') {
            app(ResultsCalculator::class)->freeze($round);
        }
    }

    public static function getEloquentQuery(): Builder
    {
        // The demo relation without the global scope. Every entry in a
        // round still being played is held, which is exactly what that scope
        // hides - eager loading it plainly returns null for the rows an admin
        // most needs to look at. Same reason SubmissionValidator::reject does
        // not go through $submission->demo.
        return parent::getEloquentQuery()->with([
            'user',
            'round.comp',
            'demo' => fn ($q) => $q->withUnreleasedComps(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompSubmissions::route('/'),
        ];
    }
}
