<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DefragliveChatMessageResource\Pages;
use App\Models\DefragliveChatMessage;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Read + moderate the live DefragLive chat from the admin panel.
 *
 * Moderation here is a soft delete: the row is dropped from the next
 * console.json rewrite (within a minute), so the message disappears from the
 * extension overlay on its next poll/reload. Instant overlay removal via the
 * ext_command/delete_message the extension already honours is a later slice.
 * AUTOMATIC blacklist filtering is unaffected - it runs upstream in the bot.
 */
class DefragliveChatMessageResource extends Resource
{
    protected static ?string $model = DefragliveChatMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'DefragLive';

    protected static ?string $navigationLabel = 'Live Chat';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        // Chat is ingested, never authored in the panel.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->poll('10s')
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'message' => 'success',
                        'command' => 'info',
                        'server_record_celebration' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable()
                    ->placeholder('--'),
                Tables\Columns\TextColumn::make('content')
                    ->wrap()
                    ->limit(120)
                    ->searchable()
                    ->placeholder('--'),
                Tables\Columns\TextColumn::make('resolved_user_id')
                    ->label('User')
                    ->placeholder('--')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'message' => 'Message',
                        'command' => 'Command',
                        'afk_notification' => 'AFK notification',
                        'afk_help' => 'AFK help',
                        'server_record_celebration' => 'Record celebration',
                        'ext_command' => 'Ext command',
                    ])
                    ->default('message'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Moderate')
                    ->modalHeading('Remove this message?')
                    ->successNotificationTitle('Message removed from chat'),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDefragliveChatMessages::route('/'),
        ];
    }
}
