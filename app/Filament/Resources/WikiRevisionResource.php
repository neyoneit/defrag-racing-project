<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WikiRevisionResource\Pages;
use App\Models\WikiRevision;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WikiRevisionResource extends Resource
{
    protected static ?string $model = WikiRevision::class;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Wiki Deleted Revisions';

    protected static ?int $navigationSort = 11;

    protected static ?string $modelLabel = 'Deleted Wiki Revision';

    protected static ?string $pluralModelLabel = 'Deleted Wiki Revisions';

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->is_moderator ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->query(
                WikiRevision::onlyTrashed()
                    ->with(['page', 'user', 'deletedByUser'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('page.title')
                    ->label('Page')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Revision Summary')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Revision Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('deletedByUser.name')
                    ->label('Deleted By')
                    ->sortable()
                    ->default('-')
                    ->formatStateUsing(fn (string $state): string => $state === '-' ? $state : UserResource::q3tohtml($state))->html(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('deleted_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('wiki_page_id')
                    ->label('Page')
                    ->relationship('page', 'title')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('deleted_by')
                    ->label('Deleted By')
                    ->relationship('deletedByUser', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Revision')
                    ->modalDescription('This will restore the deleted revision back to the page history.')
                    ->action(function (WikiRevision $record) {
                        $record->update(['deleted_by' => null]);
                        $record->restore();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeletedWikiRevisions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
