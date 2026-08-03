<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerdemoValidationResource\Pages;
use App\Models\ServerdemoValidationCase;
use App\Services\ServerdemoValidationService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The validator queue: one row per reported PLAYER.
 *
 * Reports accumulate into a case, so somebody caught on ten maps is one piece
 * of work with ten pieces of evidence rather than ten unrelated rows. The
 * ladder, the notes and the verdict all belong to the case.
 */
class ServerdemoValidationResource extends Resource
{
    protected static ?string $model = ServerdemoValidationCase::class;
    protected static ?string $slug = 'serverdemo-validations';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Validators';
    protected static ?string $navigationLabel = 'Reported players';
    protected static ?int $navigationSort = 20;
    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('serverdemo_validation') ?? false;
    }

    /**
     * What this user may see at all.
     *
     * The admin sees every case. A validator sees only cases handed to them
     * and cases opened to everyone - nothing else is listed, so nothing else
     * can be opened.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->withCount('flags')->with('assignee');

        if ($user?->isAdmin()) {
            return $query;
        }

        return $query
            ->whereNull('validation_closed_at')
            ->where(function (Builder $q) use ($user) {
                $q->where('assigned_to_user_id', $user?->id)
                    ->orWhereIn('validation_stage', ['all_validators', 'admin']);
            });
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->whereNull('validation_closed_at')->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('subject_name')
                    ->label('Player')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state, $record): string => $state
                        ? UserResource::q3tohtml($state)
                        : e($record->subjectLabel()))
                    ->html()
                    ->description(fn ($record) => $record->subject_mdd_id ? 'MDD #' . $record->subject_mdd_id : null),
                Tables\Columns\TextColumn::make('kind')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ServerdemoValidationCase::KIND_PUBLIC_DEMO ? 'Public demo' : 'Serverdemo')
                    ->color(fn (string $state): string => $state === ServerdemoValidationCase::KIND_PUBLIC_DEMO ? 'info' : 'primary'),
                Tables\Columns\TextColumn::make('flags_count')
                    ->label('Runs')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state > 2 ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('validation_stage')
                    ->label('Stage')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'assigned' => 'With one validator',
                        'second_opinion' => 'Second opinion',
                        'all_validators' => 'All validators',
                        'admin' => 'With admin',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'all_validators' => 'warning',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Holder')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '')
                    ->html(),
                Tables\Columns\TextColumn::make('validation_outcome')
                    ->label('Outcome')
                    ->badge()
                    ->placeholder('-')
                    ->color(fn (?string $state): string => match ($state) {
                        'upheld' => 'danger',
                        'dismissed' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('mine')
                    ->label('Assigned to me')
                    ->query(fn (Builder $query) => $query->where('assigned_to_user_id', auth()->id())),
                Tables\Filters\Filter::make('open')
                    ->label('Still open')
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNull('validation_closed_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Case against ' . $record->subjectLabel())
                    ->modalContent(fn ($record) => view('filament.serverdemo-validation.case-modal', ['case' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('4xl'),

                Tables\Actions\Action::make('comment')
                    ->label('Add note')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('gray')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('body')
                            ->label('Internal note')
                            ->required()
                            ->rows(3)
                            ->helperText('Only validators and the admin ever see this.'),
                    ])
                    ->action(function ($record, array $data) {
                        app(ServerdemoValidationService::class)->log($record, auth()->user(), null, $data['body']);

                        Notification::make()->success()->title('Note added')->send();
                    }),

                Tables\Actions\Action::make('handOver')
                    ->label('Not sure - pass on')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn ($record) => $record->assigned_to_user_id === auth()->id() && $record->isOpen())
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('What did you already check?')
                            ->required()
                            ->rows(3)
                            ->helperText('The next person starts from your note, so say which runs you watched and what bothers you.'),
                    ])
                    ->action(function ($record, array $data) {
                        $next = app(ServerdemoValidationService::class)->handOver($record, auth()->user(), $data['note']);

                        Notification::make()
                            ->success()
                            ->title($next ? "Passed to {$next->name}" : 'Opened to all validators')
                            ->body($next ? null : 'Nobody left who has not seen it, so everyone can look now.')
                            ->send();
                    }),

                Tables\Actions\Action::make('escalateAll')
                    ->label('Open to all validators')
                    ->icon('heroicon-o-user-group')
                    ->color('warning')
                    ->visible(fn ($record) => $record->isOpen()
                        && in_array($record->validation_stage, ['assigned', 'second_opinion'], true))
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Why (optional)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(ServerdemoValidationService::class)->escalateToAll($record, auth()->user(), $data['note'] ?? null);

                        Notification::make()->success()->title('Open to all validators')->send();
                    }),

                Tables\Actions\Action::make('callAdmin')
                    ->label('Call the admin')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->isOpen() && $record->validation_stage === 'all_validators')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Where did you get stuck?')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(ServerdemoValidationService::class)->callAdmin($record, auth()->user(), $data['note']);

                        Notification::make()->success()->title('Sent to the admin')->send();
                    }),

                Tables\Actions\Action::make('close')
                    ->label('Close case')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->isOpen()
                        && ($record->assigned_to_user_id === auth()->id() || (auth()->user()?->isAdmin() ?? false)))
                    ->form([
                        \Filament\Forms\Components\Select::make('outcome')
                            ->required()
                            ->options([
                                'upheld' => 'Upheld - the reports are right',
                                'dismissed' => 'Dismissed - the runs are fine',
                                'inconclusive' => 'Inconclusive',
                            ]),
                        \Filament\Forms\Components\Textarea::make('note')
                            ->label('Reasoning')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(ServerdemoValidationService::class)->close($record, auth()->user(), $data['outcome'], $data['note']);

                        Notification::make()->success()->title('Case closed')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServerdemoValidations::route('/'),
        ];
    }
}
