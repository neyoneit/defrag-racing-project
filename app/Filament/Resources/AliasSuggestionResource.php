<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AliasSuggestionResource\Pages;
use App\Models\AliasSuggestion;
use App\Models\UserAlias;
use App\Models\Notification as AppNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class AliasSuggestionResource extends Resource
{
    protected static ?string $model = AliasSuggestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Alias Suggestions';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Suggestion')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Target User (receives alias)')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->disabled(),

                        Forms\Components\Select::make('suggested_by_user_id')
                            ->label('Suggested By')
                            ->relationship('suggestedBy', 'name')
                            ->searchable()
                            ->disabled(),

                        Forms\Components\TextInput::make('alias')
                            ->label('Suggested Alias')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->disabled(),

                        Forms\Components\Textarea::make('note')
                            ->rows(3)
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Target User')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => \App\Filament\Resources\UserResource::q3tohtml($state))
                    ->html(),

                Tables\Columns\TextColumn::make('alias')
                    ->label('Suggested Alias')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('suggestedBy.name')
                    ->label('Suggested By')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state ? \App\Filament\Resources\UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(60)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Suggested')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('approve_on_behalf')
                    ->label('Approve on Behalf')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AliasSuggestion $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Alias on Behalf of User')
                    ->modalDescription(fn (AliasSuggestion $record) =>
                        "Add alias '{$record->alias}' to {$record->user->name}'s profile? This bypasses their consent — the user did not approve it themselves."
                    )
                    ->action(function (AliasSuggestion $record) {
                        $targetUser = $record->user;

                        // Skip if alias already exists on this user's mdd_id.
                        if ($targetUser->mdd_id && UserAlias::where('mdd_id', $targetUser->mdd_id)
                                ->where('alias', $record->alias)->exists()) {
                            $record->update(['status' => 'approved']);
                            Notification::make()
                                ->title('Alias already existed')
                                ->body('Suggestion marked approved without creating a duplicate.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Admin approve-on-behalf always creates an approved alias
                        // (bypasses the alias_restricted flag on the target user).
                        UserAlias::create([
                            'user_id' => $targetUser->id,
                            'mdd_id' => $targetUser->mdd_id,
                            'alias' => $record->alias,
                            'source' => 'manual',
                            'is_approved' => true,
                        ]);

                        $record->update(['status' => 'approved']);

                        // Tell the target user what happened so it's not silent.
                        AppNotification::create([
                            'user_id' => $targetUser->id,
                            'type' => 'alias_suggestion',
                            'before' => 'Admin',
                            'headline' => 'approved the suggested alias',
                            'after' => $record->alias,
                            'url' => route('profile.index', $targetUser->id),
                        ]);

                        Notification::make()
                            ->title('Alias approved on behalf of user')
                            ->body('Demos will be rematched during the next scheduled run.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AliasSuggestion $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Suggestion')
                    ->modalDescription(fn (AliasSuggestion $record) =>
                        "Reject alias suggestion '{$record->alias}' for {$record->user->name}?"
                    )
                    ->action(function (AliasSuggestion $record) {
                        $record->update(['status' => 'rejected']);
                        Notification::make()
                            ->title('Suggestion rejected')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_on_behalf')
                        ->label('Approve Selected on Behalf')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $created = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'pending') continue;
                                $targetUser = $record->user;
                                if (!$targetUser) continue;
                                $exists = $targetUser->mdd_id && UserAlias::where('mdd_id', $targetUser->mdd_id)
                                    ->where('alias', $record->alias)->exists();
                                if (!$exists) {
                                    UserAlias::create([
                                        'user_id' => $targetUser->id,
                                        'mdd_id' => $targetUser->mdd_id,
                                        'alias' => $record->alias,
                                        'source' => 'manual',
                                        'is_approved' => true,
                                    ]);
                                    $created++;
                                }
                                $record->update(['status' => 'approved']);
                            }
                            Notification::make()
                                ->title("Approved {$created} alias(es) on behalf of users")
                                ->body('Demos will be rematched during the next scheduled run.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reject')
                        ->label('Reject Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(fn ($r) => $r->status === 'pending' && $r->update(['status' => 'rejected']));
                            Notification::make()
                                ->title('Suggestions rejected')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAliasSuggestions::route('/'),
            'view' => Pages\ViewAliasSuggestion::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
