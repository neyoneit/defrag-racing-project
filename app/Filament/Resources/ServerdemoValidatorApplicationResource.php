<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerdemoValidatorApplicationResource\Pages;
use App\Models\ServerdemoValidatorApplication;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServerdemoValidatorApplicationResource extends Resource
{
    protected static ?string $model = ServerdemoValidatorApplication::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Validators';
    protected static ?string $navigationLabel = 'Applications';
    protected static ?int $navigationSort = 10;
    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('serverdemo_validator_applications') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ServerdemoValidatorApplication::pending()->count() ?: null;
    }

    /**
     * Cleared-out applications are still reachable here - the Deleted filter
     * decides whether they show. Everywhere else on the site they are gone,
     * which is what lets the person apply again.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Applicant')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html()
                    ->url(fn ($record) => "/profile/{$record->user_id}"),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'shortlisted' => 'info',
                        'approved' => 'success',
                        'not_selected' => 'gray',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'not_selected' ? 'Not selected' : ucfirst($state)),
                Tables\Columns\TextColumn::make('availability')
                    ->label('Around')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('contact')
                    ->placeholder('-')
                    ->copyable()
                    ->toggleable(),
                // The tally, admin-side only. Voters never see a running
                // score - see the public page.
                Tables\Columns\TextColumn::make('votes')
                    ->label('Votes')
                    ->alignCenter()
                    ->state(function ($record) {
                        $round = \App\Models\ServerdemoValidatorVoteRound::query()
                            ->latest('opened_at')
                            ->first();

                        return $round
                            ? \App\Models\ServerdemoValidatorVote::where('round_id', $round->id)
                                ->where('application_id', $record->id)
                                ->count()
                            : 0;
                    })
                    ->sortable(false)
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_validator')
                    ->label('Has permission')
                    ->boolean()
                    ->state(fn ($record) => (bool) $record->user?->hasModeratorPermission('serverdemo_validation')),
                // What this applicant's history looks like. Deleted rows are
                // counted on purpose - that is the whole reason deleting does
                // not destroy anything.
                Tables\Columns\TextColumn::make('past_rejections')
                    ->label('Turned down before')
                    ->alignCenter()
                    ->state(fn ($record) => ServerdemoValidatorApplication::withTrashed()
                        ->where('user_id', $record->user_id)
                        ->where('id', '!=', $record->id)
                        ->where('status', 'rejected')
                        ->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'shortlisted' => 'Shortlisted',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\TrashedFilter::make()
                    ->label('Deleted applications'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Application from ' . ($record->user->plain_name ?? $record->user->name ?? 'user #' . $record->user_id))
                    ->modalContent(fn ($record) => view('filament.validator-applications.detail-modal', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('3xl'),

                Tables\Actions\Action::make('setStatus')
                    ->label('Decide')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'shortlisted' => 'Shortlist (goes to the vote)',
                                'approved' => 'Approve',
                                'not_selected' => 'Not selected this round',
                                'rejected' => 'Reject',
                                'pending' => 'Back to pending',
                            ]),
                        \Filament\Forms\Components\Textarea::make('review_note')
                            ->label('Note shown to the applicant (optional)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => $data['status'],
                            'review_note' => $data['review_note'] ?? null,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()->success()->title('Application updated')->send();
                    }),

                // Approving and granting access are separate on purpose: the
                // vote decides the first, and nobody should get the permission
                // as a side effect of a status change.
                Tables\Actions\Action::make('grantPermission')
                    ->label('Grant validator access')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Makes this user a moderator with the serverdemo validation permission and nothing else. They still cannot browse the serverdemo archive - only demos of reports handed to them.')
                    ->visible(fn ($record) => $record->status === 'approved'
                        && ! ($record->user?->hasModeratorPermission('serverdemo_validation') ?? false))
                    ->action(function ($record) {
                        $user = $record->user;
                        if (! $user) {
                            Notification::make()->danger()->title('User is gone')->send();
                            return;
                        }

                        $permissions = $user->moderator_permissions ?? [];
                        $permissions[] = 'serverdemo_validation';

                        $user->update([
                            'is_moderator' => true,
                            'moderator_permissions' => array_values(array_unique($permissions)),
                        ]);

                        Notification::make()->success()->title('Validator access granted')->send();
                    }),

                Tables\Actions\Action::make('revokePermission')
                    ->label('Revoke validator access')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->user?->hasModeratorPermission('serverdemo_validation')
                        && ! ($record->user?->isAdmin() ?? false))
                    ->action(function ($record) {
                        $user = $record->user;
                        $permissions = array_values(array_diff($user->moderator_permissions ?? [], ['serverdemo_validation']));

                        // Leave is_moderator alone - they may hold other
                        // permissions, and this action is about one of them.
                        $user->update(['moderator_permissions' => $permissions]);

                        Notification::make()->success()->title('Validator access revoked')->send();
                    }),

                // Hides the application so the person may apply again. The
                // row and its rejection stay - there is deliberately no force
                // delete anywhere in this resource.
                Tables\Actions\DeleteAction::make()
                    ->label('Clear out')
                    ->modalHeading('Clear out this application')
                    ->modalDescription('It disappears from the list and they can apply again. The record of it, including a rejection, is kept and stays visible under the Deleted filter.'),

                Tables\Actions\RestoreAction::make()
                    ->label('Bring back'),
            ])
            ->bulkActions([
                // After a round closes, everyone who was not picked is marked
                // in one go. "Not selected" rather than "rejected" on purpose:
                // losing a round is not a verdict on the person, and they are
                // expected back next time.
                Tables\Actions\BulkAction::make('markNotSelected')
                    ->label('Mark as not selected')
                    ->icon('heroicon-o-minus-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('For everyone who did not get in this round. They keep their record and can apply again.')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('review_note')
                            ->label('Note shown to them (optional)')
                            ->rows(2)
                            ->default('Not selected in this round - you are welcome to apply again.'),
                    ])
                    ->action(function ($records, array $data) {
                        $touched = 0;

                        foreach ($records as $record) {
                            // Never overwrite someone who got in.
                            if ($record->status === 'approved') {
                                continue;
                            }

                            $record->update([
                                'status' => 'not_selected',
                                'review_note' => $data['review_note'] ?: null,
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                            $touched++;
                        }

                        Notification::make()
                            ->success()
                            ->title("{$touched} marked as not selected")
                            ->body('Approved applications were left alone.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServerdemoValidatorApplications::route('/'),
        ];
    }
}
