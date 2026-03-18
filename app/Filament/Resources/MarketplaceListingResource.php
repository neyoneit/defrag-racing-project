<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceListingResource\Pages;
use App\Models\MarketplaceListing;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketplaceListingResource extends Resource
{
    protected static ?string $model = MarketplaceListing::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Marketplace Listings';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('listing_type')
                    ->options(['request' => 'Request', 'offer' => 'Offer'])
                    ->required(),
                Forms\Components\Select::make('work_type')
                    ->options([
                        'map' => 'Map',
                        'player_model' => 'Player Model',
                        'weapon_model' => 'Weapon Model',
                        'shadow_model' => 'Shadow Model',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->rows(4),
                Forms\Components\TextInput::make('budget')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('assigned_to_user_id')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\BadgeColumn::make('listing_type')
                    ->colors([
                        'primary' => 'request',
                        'success' => 'offer',
                    ]),
                Tables\Columns\BadgeColumn::make('work_type')
                    ->colors([
                        'success' => 'map',
                        'warning' => 'player_model',
                        'danger' => 'weapon_model',
                        'info' => 'shadow_model',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'open',
                        'warning' => 'in_progress',
                        'primary' => 'completed',
                        'gray' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable(),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To'),
                Tables\Columns\TextColumn::make('budget'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('listing_type')
                    ->options(['request' => 'Request', 'offer' => 'Offer']),
                Tables\Filters\SelectFilter::make('work_type')
                    ->options([
                        'map' => 'Map',
                        'player_model' => 'Player Model',
                        'weapon_model' => 'Weapon Model',
                        'shadow_model' => 'Shadow Model',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListMarketplaceListings::route('/'),
            'create' => Pages\CreateMarketplaceListing::route('/create'),
            'edit' => Pages\EditMarketplaceListing::route('/{record}/edit'),
        ];
    }
}
