<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModelResource\Pages;
use App\Models\PlayerModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModelResource extends Resource
{
    protected static ?string $model = PlayerModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Models';

    protected static ?string $modelLabel = 'Model';

    protected static ?string $pluralModelLabel = 'Models';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Select::make('category')
                    ->options([
                        'player' => 'Player',
                        'weapon' => 'Weapon',
                        'shadow' => 'Shadow',
                        'item' => 'Item',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('author')
                    ->maxLength(255),
                Forms\Components\TextInput::make('author_email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('downloads')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('poly_count')
                    ->numeric(),
                Forms\Components\TextInput::make('vert_count')
                    ->numeric(),
                Forms\Components\Toggle::make('has_sounds')
                    ->required(),
                Forms\Components\Toggle::make('has_ctf_skins')
                    ->required(),
                Forms\Components\Select::make('approval_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->default('pending'),
                Forms\Components\Toggle::make('hidden')
                    ->label('Hidden')
                    ->helperText('Hide this model from public listings')
                    ->default(false),
                Forms\Components\Toggle::make('is_nsfw')
                    ->label('NSFW')
                    ->helperText('Mark as NSFW (18+) content')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('head_icon')
                    ->label('Preview')
                    ->square()
                    ->size(60)
                    ->disk('public')
                    ->getStateUsing(function ($record) {
                        // Use head icon if available, otherwise use placeholder
                        if ($record->head_icon) {
                            return $record->head_icon;
                        }
                        return null;
                    })
                    ->defaultImageUrl('/images/no-image.png'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.mdd_name')
                    ->label('Uploaded By')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'player' => 'success',
                        'weapon' => 'warning',
                        'shadow' => 'info',
                        'item' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('hidden')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_nsfw')
                    ->label('NSFW')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('downloads')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'player' => 'Player',
                        'weapon' => 'Weapon',
                        'shadow' => 'Shadow',
                        'item' => 'Item',
                    ]),
                Tables\Filters\SelectFilter::make('approval_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\TernaryFilter::make('hidden')
                    ->label('Visibility')
                    ->placeholder('All models')
                    ->trueLabel('Hidden only')
                    ->falseLabel('Visible only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['approval_status' => 'approved']))
                    ->visible(fn (PlayerModel $record) => $record->approval_status !== 'approved'),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['approval_status' => 'rejected']))
                    ->visible(fn (PlayerModel $record) => $record->approval_status !== 'rejected'),
                Tables\Actions\Action::make('hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['hidden' => true]))
                    ->visible(fn (PlayerModel $record) => !$record->hidden),
                Tables\Actions\Action::make('show')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['hidden' => false]))
                    ->visible(fn (PlayerModel $record) => $record->hidden),
                Tables\Actions\Action::make('viewModel')
                    ->icon('heroicon-o-photo')
                    ->color('warning')
                    ->label('Generate GIF')
                    ->url(fn (PlayerModel $record) => "/models/{$record->id}")
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['approval_status' => 'approved'])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModels::route('/'),
            'create' => Pages\CreateModel::route('/create'),
            'edit' => Pages\EditModel::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
