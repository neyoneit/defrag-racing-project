<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceListingResource\Pages;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceWorkType;
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

    /**
     * Every type, pending ones included: the admin must be able to see and
     * change a listing that is on one.
     */
    private static function workTypeOptions(): array
    {
        return MarketplaceWorkType::lookup()
            ->map(fn (MarketplaceWorkType $t) => $t->label . ($t->status === 'pending' ? ' (pending)' : ''))
            ->all();
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
                    ->options(fn () => static::workTypeOptions())
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
                Tables\Columns\TextColumn::make('work_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::workTypeOptions()[$state] ?? (string) $state)
                    // A type somebody suggested and nobody has approved yet is
                    // the one worth spotting in this list.
                    ->color(fn (?string $state): string => MarketplaceWorkType::find_cached($state)?->status === 'pending' ? 'warning' : 'gray'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'open',
                        'warning' => 'in_progress',
                        'primary' => 'completed',
                        'gray' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => \App\Filament\Resources\UserResource::q3tohtml($state))
                    ->html(),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To')
                    ->formatStateUsing(fn (?string $state): string => $state ? \App\Filament\Resources\UserResource::q3tohtml($state) : '-')
                    ->html(),
                Tables\Columns\TextColumn::make('budget'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('listing_type')
                    ->options(['request' => 'Request', 'offer' => 'Offer']),
                Tables\Filters\SelectFilter::make('work_type')
                    ->options(fn () => static::workTypeOptions()),
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
