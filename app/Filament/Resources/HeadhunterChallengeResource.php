<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HeadhunterChallengeResource\Pages;
use App\Filament\Resources\HeadhunterChallengeResource\RelationManagers;
use App\Models\HeadhunterChallenge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HeadhunterChallengeResource extends Resource
{
    protected static ?string $model = HeadhunterChallenge::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Headhunter Challenges';

    protected static ?string $navigationGroup = 'Community';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->rows(3),
                Forms\Components\TextInput::make('mapname')
                    ->label('Map Name')
                    ->required(),
                Forms\Components\Select::make('physics')
                    ->options(['vq3' => 'VQ3', 'cpm' => 'CPM'])
                    ->required(),
                Forms\Components\Select::make('mode')
                    ->options([
                        'run' => 'Run',
                        'strafe' => 'Strafe',
                        'freestyle' => 'Freestyle',
                        'fastcaps' => 'Fastcaps',
                        'any' => 'Any',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('target_time')
                    ->label('Target Time (milliseconds)')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('reward_amount')
                    ->numeric()
                    ->nullable(),
                Forms\Components\TextInput::make('reward_currency')
                    ->maxLength(3)
                    ->default('USD')
                    ->nullable(),
                Forms\Components\Textarea::make('reward_description')
                    ->rows(2)
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'claimed' => 'Claimed',
                        'completed' => 'Completed',
                        'disputed' => 'Disputed',
                        'closed' => 'Closed',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->nullable(),
                Forms\Components\Toggle::make('creator_banned')
                    ->label('Creator Banned from Creating Challenges'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('mapname')->label('Map')->searchable(),
                Tables\Columns\TextColumn::make('creator.username')->label('Creator')->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'open',
                        'warning' => 'claimed',
                        'info' => 'completed',
                        'danger' => 'disputed',
                        'secondary' => 'closed',
                    ]),
                Tables\Columns\TextColumn::make('participants_count')->label('Participants')->counts('participants'),
                Tables\Columns\TextColumn::make('reward_amount')->money(fn ($record) => $record->reward_currency ?? 'USD'),
                Tables\Columns\IconColumn::make('creator_banned')->boolean()->label('Banned'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'claimed' => 'Claimed',
                        'completed' => 'Completed',
                        'disputed' => 'Disputed',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\TernaryFilter::make('creator_banned')->label('Creator Banned'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListHeadhunterChallenges::route('/'),
            'create' => Pages\CreateHeadhunterChallenge::route('/create'),
            'edit' => Pages\EditHeadhunterChallenge::route('/{record}/edit'),
        ];
    }
}
