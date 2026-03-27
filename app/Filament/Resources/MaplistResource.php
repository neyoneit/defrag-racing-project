<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaplistResource\Pages;
use App\Models\Maplist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaplistResource extends Resource
{
    protected static ?string $model = Maplist::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_play_later', false);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(1000),
                Forms\Components\Toggle::make('is_public')
                    ->label('Public'),
                Forms\Components\Toggle::make('is_draft')
                    ->label('Draft'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => \App\Filament\Resources\UserResource::q3tohtml($state))
                    ->html(),
                Tables\Columns\TextColumn::make('maps_count')
                    ->label('Maps')
                    ->counts('maps')
                    ->sortable(),
                Tables\Columns\TextColumn::make('likes_count')
                    ->label('Likes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('favorites_count')
                    ->label('Favs')
                    ->sortable(),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_draft')
                    ->label('Draft')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),
                Tables\Filters\TernaryFilter::make('is_draft')
                    ->label('Draft'),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\Action::make('toggle_visibility')
                    ->label(fn (Maplist $record) => $record->is_public ? 'Hide' : 'Unhide')
                    ->icon(fn (Maplist $record) => $record->is_public ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Maplist $record) => $record->is_public ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Maplist $record) => $record->update(['is_public' => !$record->is_public])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Maplist $record) {
                        $record->favorites()->detach();
                        $record->likes()->detach();
                    })
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                $record->favorites()->detach();
                                $record->likes()->detach();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaplists::route('/'),
            'edit' => Pages\EditMaplist::route('/{record}/edit'),
        ];
    }
}
