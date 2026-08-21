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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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

    /** Everything here that needs a decision: waiting, or asked to be taken down. */
    public static function getNavigationBadge(): ?string
    {
        return (string) Wish::query()
            ->where(fn ($query) => $query->whereNull('approved_at')->orWhereNotNull('removal_requested_at'))
            ->count() ?: null;
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
            // Anything needing a decision first, whatever the sort: those are
            // the queue, the rest is archive.
            ->defaultSort('score', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->orderByRaw('removal_requested_at is not null desc')
                ->orderByRaw('approved_at is null desc'))
            // A narrow window used to squeeze the wish itself into two words
            // per line while eight one-word columns held their ground. The
            // wish column takes the slack now (nothing else asks for it) and
            // carries a width it cannot be pushed below, so a narrow window
            // scrolls sideways - which is readable - instead of wrapping the
            // one thing on the row worth reading into a stack of fragments.
            // The dates, names and badges are told not to wrap at all.
            ->columns([
                Tables\Columns\IconColumn::make('approved_at')
                    ->label('Live')
                    ->alignCenter()
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),

                // Sortable, and that is the whole point of it: the board has
                // no other stable "in the order they arrived" handle, and the
                // number is also what /wishlist/<id> links to.
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->extraCellAttributes(['style' => 'white-space: nowrap'])
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->badge()
                    ->alignCenter()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->description(fn (Wish $record) => "+{$record->upvotes} / -{$record->downvotes}"),

                Tables\Columns\TextColumn::make('project')
                    ->badge()
                    ->extraCellAttributes(['style' => 'white-space: nowrap'])
                    ->color('info')
                    ->formatStateUsing(fn (?string $state) => Wish::PROJECTS[$state] ?? $state)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    // The one column that may take the slack, and the one that
                    // must not be squeezed: a wish read two words at a time is
                    // a wish nobody can decide on.
                    ->grow()
                    ->extraCellAttributes(['style' => 'min-width: 24rem; max-width: 42rem'])
                    ->extraHeaderAttributes(['style' => 'min-width: 24rem'])
                    // nl2br because the preview is HTML, and a wish written as
                    // three short lines otherwise arrives here as one run-on
                    // sentence - which is how it reads when deciding on it.
                    ->description(fn (Wish $record) => new HtmlString(nl2br(e(
                        $record->removal_requested_at
                            ? 'REMOVAL REQUESTED' . ($record->removal_reason ? ': ' . $record->removal_reason : '')
                            : (string) str($record->body)->limit(140)
                    ))))
                    ->color(fn (Wish $record) => $record->removal_requested_at ? 'warning' : null),

                // A wish is approved before anyone else sees it, so whatever
                // it carries has to be visible at the point of approving it.
                // A link, not a preview.
                //
                // As an ImageColumn the cell was sized by the picture inside
                // it, so one wide screenshot set the width of the column for
                // every row - a lot of table given to a thumbnail too small to
                // judge anything by. The full image is one click away and that
                // is where it can actually be looked at.
                Tables\Columns\TextColumn::make('image_path')
                    ->label('Shot')
                    ->extraCellAttributes(['style' => 'white-space: nowrap'])
                    ->formatStateUsing(fn (?string $state) => $state ? 'Image' : null)
                    ->url(fn (Wish $record) => $record->imageUrl(), shouldOpenInNewTab: true)
                    ->color('info')
                    ->placeholder('-')
                    ->toggleable(),

                // Off by default: most wishes carry no video, and an empty
                // column still costs the width of its header.
                Tables\Columns\TextColumn::make('youtube_id')
                    ->label('Video')
                    ->formatStateUsing(fn (?string $state) => $state ? 'Watch' : null)
                    ->url(fn (Wish $record) => $record->youtube_id
                        ? 'https://www.youtube.com/watch?v=' . $record->youtube_id
                        : null, shouldOpenInNewTab: true)
                    ->color('info')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->extraCellAttributes(['style' => 'white-space: nowrap'])
                    // Raw, the column printed the colour codes themselves:
                    // ^3[^nS^mH^3]^7neyo^4. instead of a nick.
                    ->formatStateUsing(fn (?string $state) => UserResource::q3tohtml($state ?? ''))
                    ->html()
                    // Searching the coloured name never matched what is on
                    // screen, since the codes sit between the letters.
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'user',
                        fn ($q) => $q->where('plain_name', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                    )),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Wish::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'planned' => 'info',
                        'done' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                // "3 days ago" in a column, the full stamp in the tooltip: the
                // date only matters as "how old is this", and spelled out it
                // was the widest thing in the row after the wish itself.
                // Empty until a wish is done, which is the point: sort by it
                // and the Done tab reads newest-finished first, the same order
                // the public board uses.
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Done')
                    ->extraCellAttributes(['style' => 'white-space: nowrap'])
                    ->since()
                    ->placeholder('-')
                    ->tooltip(fn (Wish $record) => $record->completed_at?->toDayDateTimeString())
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Age')
                    ->extraCellAttributes(['style' => 'white-space: nowrap'])
                    ->since()
                    ->tooltip(fn (Wish $record) => $record->created_at?->toDayDateTimeString())
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('approved_at')
                    ->label('Approval')
                    ->placeholder('All')
                    ->trueLabel('Live on the list')
                    ->falseLabel('Waiting for approval')
                    ->nullable(),
                Tables\Filters\Filter::make('removal_requested')
                    ->label('Removal requested')
                    ->query(fn ($query) => $query->whereNotNull('removal_requested_at')),
                Tables\Filters\SelectFilter::make('project')->options(Wish::PROJECTS),
                Tables\Filters\SelectFilter::make('status')->options(Wish::STATUSES),
            ])
            // One dropdown instead of five labelled buttons. Spelled out, the
            // row was wider than the wish it was about and pushed the text off
            // a laptop screen.
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // Approval is the gate: until this is pressed the wish is
                    // not on the public list and cannot be voted on.
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

                    // The author asked for it to go. Either it goes, or the
                    // request is turned down and the wish stays as it was.
                    Tables\Actions\Action::make('declineRemoval')
                        ->label('Keep it (decline)')
                        ->icon('heroicon-o-hand-raised')
                        ->color('gray')
                        ->visible(fn (Wish $record) => $record->removal_requested_at !== null)
                        ->requiresConfirmation()
                        ->action(function (Wish $record) {
                            $record->update(['removal_requested_at' => null, 'removal_reason' => null]);

                            Notification::make()->success()->title('Removal request declined')->send();
                        }),

                    Tables\Actions\EditAction::make(),

                    // The common case is answering a wish without rewriting it,
                    // so it gets its own one-field action instead of the form.
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
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray'),
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
