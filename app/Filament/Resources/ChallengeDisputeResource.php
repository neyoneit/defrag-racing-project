<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChallengeDisputeResource\Pages;
use App\Models\ChallengeDispute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChallengeDisputeResource extends Resource
{
    protected static ?string $model = ChallengeDispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Challenge Disputes';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('challenge_disputes') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dispute Details')
                    ->schema([
                        Forms\Components\TextInput::make('challenge.title')
                            ->label('Challenge')
                            ->disabled(),
                        Forms\Components\TextInput::make('claimer.username')
                            ->label('Filed By')
                            ->disabled(),
                        Forms\Components\TextInput::make('creator.username')
                            ->label('Creator')
                            ->disabled(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\Textarea::make('evidence')
                            ->label('Evidence')
                            ->rows(3)
                            ->disabled(),
                    ]),
                Forms\Components\Section::make('Creator Response')
                    ->schema([
                        Forms\Components\Textarea::make('creator_response')
                            ->label('Creator Response')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('creator_responded_at')
                            ->label('Responded At')
                            ->disabled(),
                    ]),
                Forms\Components\Section::make('Resolution')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'resolved_paid' => 'Resolved - Paid',
                                'resolved_unpaid' => 'Resolved - Unpaid',
                                'auto_banned' => 'Auto Banned',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3),
                        Forms\Components\Toggle::make('ban_creator')
                            ->label('Ban creator from creating challenges')
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('challenge.title')
                    ->label('Challenge')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('claimer.username')
                    ->label('Filed By')
                    ->searchable(),
                Tables\Columns\TextColumn::make('creator.username')
                    ->label('Creator')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'resolved_paid' => 'success',
                        'resolved_unpaid' => 'info',
                        'auto_banned' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('creator_responded')
                    ->label('Response')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->creator_responded_at !== null),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Filed At'),
                Tables\Columns\TextColumn::make('days_remaining')
                    ->label('Days Left')
                    ->getStateUsing(fn ($record) => $record->daysUntilAutoBan())
                    ->badge()
                    ->color(fn ($state) => $state !== null && $state <= 3 ? 'danger' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'resolved_paid' => 'Resolved - Paid',
                        'resolved_unpaid' => 'Resolved - Unpaid',
                        'auto_banned' => 'Auto Banned',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resolve_paid')
                    ->label('Resolve (Paid)')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'resolved_paid',
                            'resolved_at' => now(),
                            'resolved_by_admin_id' => auth()->id(),
                        ]);
                    }),
                Tables\Actions\Action::make('resolve_ban')
                    ->label('Ban Creator')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'auto_banned',
                            'resolved_at' => now(),
                            'resolved_by_admin_id' => auth()->id(),
                        ]);

                        \App\Models\HeadhunterChallenge::where('creator_id', $record->creator_id)
                            ->update(['creator_banned' => true]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChallengeDisputes::route('/'),
            'edit' => Pages\EditChallengeDispute::route('/{record}/edit'),
        ];
    }
}
