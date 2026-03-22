<?php

namespace App\Filament\Resources\TagResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MapsRelationManager extends RelationManager
{
    protected static string $relationship = 'maps';

    protected static ?string $title = 'Maps with this tag';

    public function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => route('maps.map', $record->name))
                    ->openUrlInNewTab()
                    ->label('Map Name'),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gametype')
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Tagged At'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Remove tag'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label('Remove tag from selected'),
            ]);
    }
}
