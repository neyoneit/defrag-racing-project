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
                        DefragliveContest::STATUS_DONATED => 'Donated to the site',
                        DefragliveContest::STATUS_FORWARDED => 'Forwarded to the next winner',
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
                        DefragliveContest::STATUS_DONATED => 'success',
                        DefragliveContest::STATUS_FORWARDED => 'info',
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

                // Settle a drawn contest's prize: paid out, donated back to the
                // site by the winner, or rolled over into the next contest's
                // prize pool (bump that contest's prize_amount manually).
                Tables\Actions\Action::make('resolvePrize')
                    ->label('Resolve prize')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\Radio::make('resolution')
                            ->label('How was the prize settled?')
                            ->options([
                                DefragliveContest::STATUS_PAID => 'Paid to the winner',
                                DefragliveContest::STATUS_DONATED => 'Donated to the site',
                                DefragliveContest::STATUS_FORWARDED => 'Forwarded to the next winner',
                            ])
                            ->descriptions([
                                DefragliveContest::STATUS_FORWARDED => 'Adds this prize to the next upcoming contest\'s pool, shown publicly as "carried over from previous winners".',
                            ])
                            ->default(DefragliveContest::STATUS_PAID)
                            ->required()
                            ->live(),
                        Forms\Components\Checkbox::make('create_donation')
                            ->label('Create a site donation record in the winner\'s name')
                            ->helperText('Shows up immediately in the site donations progress as approved.')
                            ->default(true)
                            ->visible(fn (Forms\Get $get) => $get('resolution') === DefragliveContest::STATUS_DONATED),
                    ])
                    ->visible(fn (DefragliveContest $r) => $r->winner_name !== null
                        && ! in_array($r->status, DefragliveContest::RESOLVED_STATUSES, true))
                    ->action(function (DefragliveContest $record, array $data) {
                        $record->update(['status' => $data['resolution']]);
                        $plainWinner = trim(preg_replace('/\^[0-9A-Za-z]/', '', (string) $record->winner_name));

                        if ($data['resolution'] === DefragliveContest::STATUS_DONATED) {
                            if (empty($data['create_donation'])) {
                                Notification::make()->title('Marked as donated')
                                    ->body('No donation record created (checkbox was off).')
                                    ->success()->send();

                                return;
                            }
                            // Credit the prize back as a real site donation so it
                            // shows up in the donations progress right away.
                            // donor_email matters: isDonor()/getDonationTotal()
                            // aggregate a user's donations by email match, so
                            // without it the prize wouldn't count toward the
                            // winner's donor stats.
                            \App\Models\SiteDonation::create([
                                'user_id' => $record->winner_user_id,
                                'donor_email' => $record->winner?->email,
                                'donor_name' => $plainWinner ?: 'DefragLive contest winner',
                                'amount' => $record->prize_amount,
                                'currency' => $record->prize_currency,
                                'donation_date' => now()->toDateString(),
                                'note' => "DefragLive contest prize donated back ({$record->title}) - "
                                    . url('/defraglive/contest'),
                                'status' => 'approved',
                            ]);
                            Notification::make()->title('Marked as donated')
                                ->body("Site donation of {$record->prize_amount} {$record->prize_currency} recorded for {$plainWinner}.")
                                ->success()->send();

                            return;
                        }

                        if ($data['resolution'] === DefragliveContest::STATUS_FORWARDED) {
                            // Roll the prize into the next contest (same currency,
                            // starting after this one; draft or active).
                            $next = DefragliveContest::query()
                                ->whereNull('winner_name')
                                ->whereKeyNot($record->getKey())
                                ->where('prize_currency', $record->prize_currency)
                                ->whereIn('status', [DefragliveContest::STATUS_DRAFT, DefragliveContest::STATUS_ACTIVE])
                                ->where('starts_at', '>=', $record->starts_at)
                                ->orderBy('starts_at')
                                ->first();

                            if ($next) {
                                $next->update([
                                    'prize_amount' => $next->prize_amount + $record->prize_amount,
                                    // Tracked separately so the public page can
                                    // show "base + carried over from previous
                                    // winners" instead of one opaque number.
                                    'carried_over_amount' => $next->carried_over_amount + $record->prize_amount,
                                ]);
                                Notification::make()->title('Marked as forwarded')
                                    ->body("Prize added to \"{$next->title}\" - its pool is now {$next->fresh()->prize_amount} {$next->prize_currency} (of which {$next->fresh()->carried_over_amount} carried over).")
                                    ->success()->send();
                            } else {
                                Notification::make()->title('Marked as forwarded - but no upcoming contest found')
                                    ->body('Create the next contest and raise its prize manually by this amount.')
                                    ->warning()->send();
                            }

                            return;
                        }

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
