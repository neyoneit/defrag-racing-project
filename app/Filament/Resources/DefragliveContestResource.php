<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DefragliveContestResource\Pages;
use App\Models\DefragliveContest;
use App\Services\DefragliveWatchService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DefragliveContestResource extends Resource
{
    protected static ?string $model = DefragliveContest::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'DefragLive';

    protected static ?string $navigationLabel = 'Watch Contests';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contest')->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->seconds(false)
                    ->default(now())
                    ->helperText('Server time is UTC. Defaults to now so the contest is live immediately.')
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->seconds(false)
                    ->default(now()->addDays(14))
                    ->required()
                    ->after('starts_at'),
                Forms\Components\TextInput::make('prize_amount')
                    ->numeric()
                    ->default(5)
                    ->required(),
                Forms\Components\TextInput::make('prize_currency')
                    ->default('USD')
                    ->maxLength(8)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        DefragliveContest::STATUS_DRAFT => 'Draft',
                        DefragliveContest::STATUS_ACTIVE => 'Active',
                        DefragliveContest::STATUS_CLOSED => 'Closed',
                        DefragliveContest::STATUS_PAID => 'Paid',
                    ])
                    ->default(DefragliveContest::STATUS_DRAFT)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Winner (set at draw)')
                ->schema([
                    Forms\Components\TextInput::make('winner_name')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('winner_seconds')->disabled()->dehydrated(false)
                        ->suffix('seconds watched'),
                    Forms\Components\TextInput::make('winner_tickets')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('total_tickets')->disabled()->dehydrated(false),
                    Forms\Components\TextInput::make('winning_ticket')->disabled()->dehydrated(false),
                    Forms\Components\DateTimePicker::make('drawn_at')->disabled()->dehydrated(false),
                ])
                ->columns(2)
                ->visible(fn (?DefragliveContest $record) => $record?->winner_name !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('starts_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime('M j, H:i')->sortable(),
                Tables\Columns\TextColumn::make('ends_at')->dateTime('M j, H:i')->sortable(),
                Tables\Columns\TextColumn::make('prize_amount')
                    ->formatStateUsing(fn ($state, DefragliveContest $r) => ($r->prize_currency === 'USD' ? '$' : '') . $state . ($r->prize_currency !== 'USD' ? ' ' . $r->prize_currency : '')),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        DefragliveContest::STATUS_ACTIVE => 'success',
                        DefragliveContest::STATUS_CLOSED => 'warning',
                        DefragliveContest::STATUS_PAID => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('winner_name')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state, DefragliveContest $r) => $state
                        ? preg_replace('/\^[0-9A-Za-z]/', '', $state) . ($r->winner_tickets ? " ({$r->winner_tickets}/{$r->total_tickets})" : '')
                        : '-'),
            ])
            ->actions([
                // Run the watch-time-weighted raffle. Available once a period is
                // closed (or active, e.g. an early draw) and not yet drawn.
                Tables\Actions\Action::make('draw')
                    ->label('Draw winner')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Draw the raffle winner now? More watch time = more tickets, but any entrant can win. This sets the winner and closes the contest.')
                    ->visible(fn (DefragliveContest $r) => $r->winner_name === null)
                    ->action(function (DefragliveContest $record) {
                        $winner = app(DefragliveWatchService::class)->draw($record);
                        if (!$winner) {
                            Notification::make()->title('No eligible entrants')
                                ->body('Nobody accrued at least one ticket (1 minute watched). Contest left undrawn.')
                                ->warning()->send();

                            return;
                        }
                        Notification::make()->title('Winner drawn')
                            ->body(preg_replace('/\^[0-9A-Za-z]/', '', $winner['name']) . " - {$winner['tickets']} tickets, won ticket {$record->fresh()->winning_ticket} of {$record->fresh()->total_tickets}.")
                            ->success()->send();
                    }),

                // Mark a drawn contest as paid out.
                Tables\Actions\Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DefragliveContest $r) => $r->winner_name !== null && $r->status !== DefragliveContest::STATUS_PAID)
                    ->action(function (DefragliveContest $record) {
                        $record->update(['status' => DefragliveContest::STATUS_PAID]);
                        Notification::make()->title('Marked as paid')->success()->send();
                    }),

                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListDefragliveContests::route('/'),
            'create' => Pages\CreateDefragliveContest::route('/create'),
            'edit' => Pages\EditDefragliveContest::route('/{record}/edit'),
        ];
    }
}
