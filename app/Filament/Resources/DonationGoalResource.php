<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationGoalResource\Pages;
use App\Filament\Resources\DonationGoalResource\RelationManagers;
use App\Models\DonationGoal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DonationGoalResource extends Resource
{
    protected static ?string $model = DonationGoal::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Donation Goals';

    protected static ?int $navigationSort = 17;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('year')
                    ->label('Year')
                    ->required()
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->default(now()->year),
                Forms\Components\TextInput::make('yearly_goal')
                    ->label('Yearly Goal Amount')
                    ->required()
                    ->numeric()
                    ->prefix('€')
                    ->default(1200.00),
                Forms\Components\Select::make('currency')
                    ->options([
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                        'CZK' => 'CZK',
                    ])
                    ->default('EUR')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('year')
                    ->label('Year')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('yearly_goal')
                    ->label('Yearly Goal')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('year', 'desc')
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDonationGoals::route('/'),
            'create' => Pages\CreateDonationGoal::route('/create'),
            'edit' => Pages\EditDonationGoal::route('/{record}/edit'),
        ];
    }
}
