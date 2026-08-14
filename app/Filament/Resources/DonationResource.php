<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationResource\Pages;
use App\Models\SiteDonation;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DonationResource extends Resource
{
    protected static ?string $model = SiteDonation::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Donations';

    protected static ?int $navigationSort = 15;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('donor_name')
                    ->label('Donor Name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('donor_email')
                    ->label('Donor Email (PayPal)')
                    ->email()
                    ->helperText('PayPal email. Automatically links to users who have this email in their Donation Emails.'),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('€'),
                Forms\Components\Select::make('currency')
                    ->options([
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                        'CZK' => 'CZK',
                    ])
                    ->default('EUR')
                    ->required(),
                Forms\Components\DatePicker::make('donation_date')
                    ->label('Donation Date')
                    ->required()
                    ->default(now()),
                Forms\Components\Textarea::make('note')
                    ->label('Note')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->required(),

                // How much of this money buys comps prizes, and for how long.
                //
                // None of it can be worked out from the amount: 150 EUR is ten
                // weeks at 15 or thirty at 5, and only whoever took the money
                // knows which was agreed. Three fields, all optional - a
                // donation that has nothing to do with comps just leaves them
                // empty and pays for running the site as before.
                Forms\Components\Section::make('Comps prize pool')
                    ->description('Leave empty unless some of this donation is earmarked for comps. Both physics are paid, so the per-week total is split in two.')
                    ->schema([
                        Forms\Components\TextInput::make('comps_amount')
                            ->label('Amount going to comps')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('€')
                            ->live(onBlur: true)
                            ->helperText('Can be the whole donation or part of it. The rest pays for running the site.'),
                        Forms\Components\TextInput::make('comps_weeks')
                            ->label('Spread over how many weeklies')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(520)
                            ->live(onBlur: true)
                            ->requiredWith('comps_start_comp'),
                        Forms\Components\TextInput::make('comps_start_comp')
                            ->label('First weekly it applies to')
                            ->numeric()
                            ->minValue(1)
                            ->live(onBlur: true)
                            ->default(fn () => ((int) \App\Models\Comp::weekly()->max('number')) + 1)
                            ->requiredWith('comps_weeks')
                            ->helperText('Weekly number, not a date. A week that already exists keeps the prize it was created with - raise that one on the comps control page.'),

                        // Shown publicly on the comps page, under the donor's
                        // name. Separate from the donation's own note, which is
                        // about the donation as a whole and often carries a
                        // thank-you or a payment reference - neither belongs
                        // next to a prize pool.
                        Forms\Components\TextInput::make('comps_note')
                            ->label('Public note on the comps page')
                            ->maxLength(160)
                            ->columnSpanFull()
                            ->helperText('Optional, shown under the donor name on /comps. Leave empty and the entry stays a single line.'),

                        // The arithmetic, spelled out before saving. Entering
                        // 150 over 10 weeks and finding out later it meant
                        // 7.50 a physics is exactly the kind of surprise this
                        // panel exists to prevent.
                        Forms\Components\Placeholder::make('comps_breakdown')
                            ->label('What this pays')
                            ->columnSpanFull()
                            ->content(function (Forms\Get $get): string {
                                $amount = (float) $get('comps_amount');
                                $weeks = (int) $get('comps_weeks');
                                $start = (int) $get('comps_start_comp');

                                if ($amount <= 0 || $weeks < 1 || $start < 1) {
                                    return 'Fill in all three fields to see the split.';
                                }

                                $perWeek = round($amount / $weeks, 2);
                                $perPhysics = round($perWeek / 2, 2);
                                $end = $start + $weeks - 1;

                                return sprintf(
                                    '€%s per week (€%s per physics), weeklies %d to %d.',
                                    rtrim(rtrim(number_format($perWeek, 2, '.', ''), '0'), '.'),
                                    rtrim(rtrim(number_format($perPhysics, 2, '.', ''), '0'), '.'),
                                    $start,
                                    $end
                                );
                            }),
                    ])
                    ->columns(3)
                    ->collapsed(fn (?SiteDonation $record) => ! $record || $record->comps_amount <= 0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('donor_name')
                    ->label('Donor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('donor_email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->placeholder('--'),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('comps_amount')
                    ->label('To comps')
                    // Null rather than 0.00 for the ordinary donation, so the
                    // column reads as "these ones fund comps" at a glance
                    // instead of a wall of zeroes.
                    ->getStateUsing(fn (SiteDonation $r) => $r->comps_amount > 0 ? $r->comps_amount : null)
                    ->money('EUR')
                    ->description(fn (SiteDonation $r): ?string => $r->comps_weeks
                        ? sprintf('weeklies %d-%d', $r->comps_start_comp, $r->compsEndComp())
                        : null)
                    ->placeholder('--')
                    ->sortable(),
                Tables\Columns\TextColumn::make('donation_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('donation_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SiteDonation $record): bool => $record->status === 'pending')
                    ->action(fn (SiteDonation $record) => $record->update(['status' => 'approved']))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SiteDonation $record): bool => $record->status === 'pending')
                    ->action(fn (SiteDonation $record) => $record->update(['status' => 'rejected']))
                    ->requiresConfirmation(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListDonations::route('/'),
            'create' => Pages\CreateDonation::route('/create'),
            'edit' => Pages\EditDonation::route('/{record}/edit'),
        ];
    }
}
