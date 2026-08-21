<?php

namespace App\Filament\Resources;

use App\Filament\Pages\CommunityTaskReview;
use App\Filament\Resources\CommunityTaskReviewResource\Pages;
use App\Models\CommunityTaskVote;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CommunityTaskReviewResource extends Resource
{
    protected static ?string $model = CommunityTaskVote::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Task Reviews';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->is_moderator;
    }

    public static function getNavigationBadge(): ?string
    {
        return CommunityTaskVote::where('consensus_status', 'needs_review')
            ->distinct()
            ->count('demo_id') ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * One row per DEMO, not per vote.
     *
     * The comment here used to promise this and the query did not do it, so a
     * demo four people had voted on filled four rows of the queue - each one
     * carrying the same vote summary, each one a button that resolved all four
     * votes at once. Three of those rows were the same decision made twice
     * more, and the count in the navigation badge was votes rather than work.
     *
     * The kept row is the newest vote on each demo, which is the one that
     * triggered the review.
     */
    public static function getEloquentQuery(): Builder
    {
        $latest = CommunityTaskVote::query()
            ->selectRaw('MAX(id)')
            ->groupBy('demo_id');

        return parent::getEloquentQuery()
            ->whereIn('id', $latest)
            ->with(['user', 'demo', 'selectedRecord', 'resolver']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('consensus_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'needs_review' => 'warning',
                        'resolved' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('demo.map_name')
                    ->label('Map')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('demo.player_name')
                    ->label('Demo Player')
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\TextColumn::make('demo.time_ms')
                    ->label('Demo Time')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        $m = floor($state / 60000);
                        $s = floor(($state % 60000) / 1000);
                        $ms = $state % 1000;
                        return sprintf('%02d:%02d.%03d', $m, $s, $ms);
                    }),

                Tables\Columns\TextColumn::make('vote_type')
                    ->label('Vote')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'no_match' => 'danger',
                        'not_sure' => 'warning',
                        'unassign' => 'danger',
                        'assign' => 'success',
                        'correct' => 'success',
                        'better_match' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('task_type')
                    ->label('Task')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'assignment' => 'info',
                        'verification' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('vote_count')
                    ->label('Votes')
                    ->getStateUsing(function (CommunityTaskVote $record): string {
                        $counts = CommunityTaskVote::where('demo_id', $record->demo_id)
                            ->select('vote_type', DB::raw('count(*) as cnt'))
                            ->groupBy('vote_type')
                            ->pluck('cnt', 'vote_type')
                            ->toArray();
                        $parts = [];
                        foreach ($counts as $type => $cnt) {
                            $parts[] = "{$cnt}x {$type}";
                        }
                        return implode(', ', $parts);
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Last Voter')
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('consensus_status')
                    ->options([
                        'needs_review' => 'Needs Review',
                        'resolved' => 'Resolved',
                        'archived' => 'Archived',
                    ])
                    ->default('needs_review'),

                Tables\Filters\SelectFilter::make('vote_type')
                    ->options([
                        'no_match' => 'No Match',
                        'not_sure' => 'Not Sure',
                        'unassign' => 'Unassign',
                    ]),
            ])
            ->actions([
                // One button, and it opens a page.
                //
                // There were four: a modal listing the votes (with a Submit
                // button that submitted nothing), a modal listing the map's
                // records, and an Assign form whose only real field was a
                // record id typed in by hand - an id that lived in the second
                // modal, which had to be closed before the third could open.
                // Deciding meant copying a number between two dialogs while
                // remembering what the votes had said.
                //
                // All of it is on the review page now, which also carries what
                // none of the modals had: the record the demo is on today and
                // whether any record holder has ever used the name written in
                // the demo. See CommunityTaskReview.
                Tables\Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (CommunityTaskVote $record) => CommunityTaskReview::getUrl() . '?demo=' . $record->demo_id),
            ])
            ->bulkActions([])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunityTaskReviews::route('/'),
        ];
    }
}
