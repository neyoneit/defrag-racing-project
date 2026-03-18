<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MapperClaimResource\Pages;
use App\Models\MapperClaim;
use App\Models\Map;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MapperClaimResource extends Resource
{
    protected static ?string $model = MapperClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Creator Claims';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'plain_name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The author name to match against maps.author'),
                Forms\Components\Select::make('type')
                    ->options([
                        'map' => 'Map',
                        'model' => 'Model',
                    ])
                    ->required()
                    ->default('map'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.plain_name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->url(fn (MapperClaim $record) => route('profile.index', $record->user_id)),
                Tables\Columns\TextColumn::make('name')
                    ->label('Claimed Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'map' => 'success',
                        'model' => 'info',
                    }),
                Tables\Columns\TextColumn::make('matching_maps_count')
                    ->label('Matching Maps')
                    ->getStateUsing(fn (MapperClaim $record) => Map::where('visible', true)
                        ->where('author', 'REGEXP', MapperClaim::authorRegexp($record->name))
                        ->count()
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'map' => 'Map',
                        'model' => 'Model',
                    ]),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Remove Claim'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMapperClaims::route('/'),
        ];
    }
}
