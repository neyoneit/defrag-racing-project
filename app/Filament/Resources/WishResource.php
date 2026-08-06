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

    /** Waiting wishes are the only thing here that needs doing. */
    public static function getNavigationBadge(): ?string
    {
        return (string) Wish::whereNull('approved_at')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
            // Waiting first, whatever the sort: they are the queue, the rest
            // is archive.
            ->defaultSort('score', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->orderByRaw('approved_at is null desc'))
            ->columns([
                Tables\Columns\IconColumn::make('approved_at')
                    ->label('Live')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),

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
                Tables\Filters\TernaryFilter::make('approved_at')
                    ->label('Approval')
                    ->placeholder('All')
                    ->trueLabel('Live on the list')
                    ->falseLabel('Waiting for approval')
                    ->nullable(),
                Tables\Filters\SelectFilter::make('project')->options(Wish::PROJECTS),
                Tables\Filters\SelectFilter::make('status')->options(Wish::STATUSES),
            ])
            ->actions([
                // Approval is the gate: until this is pressed the wish is not
                // on the public list and cannot be voted on.
                Tables\Actions\Action::make('approve')
                    ->label(fn (Wish $record) => $record->isApproved() ? 'Take off the list' : 'Approve')
                    ->icon(fn (Wish $record) => $record->isApproved() ? 'heroicon-o-eye-slash' : 'heroicon-o-check-circle')
                    ->color(fn (Wish $record) => $record->isApproved() ? 'gray' : 'success')
                    ->requiresConfirmation(fn (Wish $record) => $record->isApproved())
                    ->action(function (Wish $record) {
                        if ($record->isApproved()) {
                            $record->update(['approved_at' => null, 'approved_by' => null]);

                            Notification::make()->success()->title('Taken off the list')->send();

                            return;
                        }

                        $record->update(['approved_at' => now(), 'approved_by' => auth()->id()]);

                        Notification::make()->success()->title('Wish approved')->send();
                    }),

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
                    Tables\Actions\BulkAction::make('approveAll')
                        ->label('Approve selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ])),

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
