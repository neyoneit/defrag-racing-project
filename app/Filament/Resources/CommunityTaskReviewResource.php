<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunityTaskReviewResource\Pages;
use App\Models\CommunityTaskVote;
use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CommunityTaskReviewResource extends Resource
{
    protected static ?string $model = CommunityTaskVote::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Task Reviews';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->is_moderator;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()
            ->where('consensus_status', 'needs_review')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        // Group votes by demo_id, show one row per demo that needs review
        // We show the latest vote per demo that triggered the review
        return parent::getEloquentQuery()
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
                Tables\Actions\Action::make('viewVotes')
                    ->label('Votes')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (CommunityTaskVote $record) => "Votes for demo #{$record->demo_id}")
                    ->modalContent(function (CommunityTaskVote $record) {
                        $votes = CommunityTaskVote::where('demo_id', $record->demo_id)
                            ->with(['user', 'selectedRecord'])
                            ->orderBy('created_at')
                            ->get();

                        $html = '<div class="space-y-2">';
                        foreach ($votes as $vote) {
                            $userName = $vote->user ? UserResource::q3tohtml($vote->user->name) : 'Unknown';
                            $recordInfo = $vote->selectedRecord
                                ? " → #{$vote->selectedRecord->rank} ({$vote->selectedRecord->name})"
                                : '';
                            $html .= '<div class="flex items-center justify-between p-2 bg-gray-800 rounded">';
                            $html .= "<div>{$userName}</div>";
                            $html .= "<div class='flex items-center gap-2'>";
                            $html .= "<span class='px-2 py-0.5 text-xs rounded bg-gray-700'>{$vote->vote_type}</span>";
                            $html .= "<span class='text-xs text-gray-400'>{$recordInfo}</span>";
                            $html .= "<span class='text-xs text-gray-500'>{$vote->created_at->diffForHumans()}</span>";
                            $html .= "</div></div>";
                        }
                        $html .= '</div>';

                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->modalWidth('lg'),

                Tables\Actions\Action::make('assignRecord')
                    ->label('Assign')
                    ->icon('heroicon-o-link')
                    ->color('success')
                    ->visible(fn (CommunityTaskVote $record) => $record->consensus_status === 'needs_review')
                    ->form([
                        Forms\Components\TextInput::make('record_id')
                            ->label('Record ID to assign')
                            ->numeric()
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->action(function (CommunityTaskVote $record, array $data) {
                        $demo = $record->demo;
                        $recordModel = \App\Models\Record::find($data['record_id']);

                        if (!$demo || !$recordModel) {
                            Notification::make()->title('Invalid demo or record')->danger()->send();
                            return;
                        }

                        if ($demo->offlineRecord) {
                            $demo->offlineRecord->delete();
                        }

                        $demo->update([
                            'record_id' => $recordModel->id,
                            'status' => 'assigned',
                            'manually_assigned' => true,
                        ]);

                        RenderedVideo::where('demo_id', $demo->id)->update(['record_id' => $recordModel->id]);

                        // Resolve all votes for this demo
                        CommunityTaskVote::where('demo_id', $record->demo_id)
                            ->whereIn('consensus_status', ['needs_review', null])
                            ->update([
                                'consensus_status' => 'resolved',
                                'resolved_by' => auth()->id(),
                                'resolved_at' => now(),
                                'admin_notes' => $data['admin_notes'] ?? null,
                            ]);

                        Notification::make()
                            ->title('Demo assigned')
                            ->body("Demo #{$demo->id} assigned to record #{$recordModel->id}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('markCorrect')
                    ->label('Correct')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CommunityTaskVote $record) => $record->consensus_status === 'needs_review')
                    ->requiresConfirmation()
                    ->modalDescription('Mark this demo assignment as correct and archive all votes.')
                    ->action(function (CommunityTaskVote $record) {
                        CommunityTaskVote::where('demo_id', $record->demo_id)
                            ->whereIn('consensus_status', ['needs_review', null])
                            ->update([
                                'consensus_status' => 'archived',
                                'resolved_by' => auth()->id(),
                                'resolved_at' => now(),
                                'admin_notes' => 'Confirmed correct by admin',
                            ]);

                        Notification::make()
                            ->title('Marked as correct')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (CommunityTaskVote $record) => $record->consensus_status === 'needs_review')
                    ->requiresConfirmation()
                    ->action(function (CommunityTaskVote $record) {
                        CommunityTaskVote::where('demo_id', $record->demo_id)
                            ->whereIn('consensus_status', ['needs_review', null])
                            ->update([
                                'consensus_status' => 'archived',
                                'resolved_by' => auth()->id(),
                                'resolved_at' => now(),
                                'admin_notes' => 'Dismissed by admin',
                            ]);

                        Notification::make()
                            ->title('Dismissed')
                            ->info()
                            ->send();
                    }),
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
