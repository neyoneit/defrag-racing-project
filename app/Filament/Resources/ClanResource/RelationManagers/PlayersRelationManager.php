<?php

namespace App\Filament\Resources\ClanResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PlayersRelationManager extends RelationManager
{
    protected static string $relationship = 'players';

    protected static ?string $title = 'Clan Members';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Player')
                    ->options(User::pluck('username', 'id'))
                    ->searchable()
                    ->required()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.username')
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Member')
                    ->modalHeading('Add Member to Clan')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['clan_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Remove')
                    ->modalHeading('Remove Member from Clan')
                    ->successNotificationTitle('Member removed from clan'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Remove Selected')
                        ->modalHeading('Remove Selected Members from Clan')
                        ->successNotificationTitle('Members removed from clan'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
