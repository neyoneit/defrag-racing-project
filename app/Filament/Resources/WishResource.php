<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WishResource\Pages;
use App\Models\Wish;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The admin side of the wishlist: answering wishes and removing the ones that
 * do not belong.
 *
 * There is no create action. A wish written from the admin panel would carry
 * the weight of the site behind it while sitting in a list that is supposed to
 * measure what other people want.
 */
class WishResource extends Resource
{
    protected static ?string $model = Wish::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Wishlist';

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
        return $form->schema([
            Forms\Components\Select::make('project')
                ->options(Wish::PROJECTS)
                ->required(),

            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(120),

            Forms\Components\Textarea::make('body')
                ->rows(5)
                ->required(),

            Forms\Components\Select::make('status')
                ->options(Wish::STATUSES)
                ->required(),

            Forms\Components\Textarea::make('status_note')
                ->label('Your answer (shown publicly under the wish)')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('score', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->badge()
                    ->alignCenter()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->description(fn (Wish $record) => "+{$record->upvotes} / -{$record->downvotes}"),

                Tables\Columns\TextColumn::make('project')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state) => Wish::PROJECTS[$state] ?? $state),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Wish $record) => str($record->body)->limit(140)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Wish::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'planned' => 'info',
                        'done' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')->options(Wish::PROJECTS),
                Tables\Filters\SelectFilter::make('status')->options(Wish::STATUSES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // The common case is answering a wish without rewriting it, so
                // it gets its own one-field action instead of the full form.
                Tables\Actions\Action::make('answer')
                    ->label('Set status')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options(Wish::STATUSES)
                            ->required(),
                        Forms\Components\Textarea::make('status_note')
                            ->label('Answer (public, optional)')
                            ->rows(3),
                    ])
                    ->fillForm(fn (Wish $record) => [
                        'status' => $record->status,
                        'status_note' => $record->status_note,
                    ])
                    ->action(function (Wish $record, array $data) {
                        $record->update($data);

                        Notification::make()->success()->title('Wish updated')->send();
                    }),

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
            'index' => Pages\ListWishes::route('/'),
            'edit' => Pages\EditWish::route('/{record}/edit'),
        ];
    }
}
