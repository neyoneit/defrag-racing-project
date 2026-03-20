<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MapperClaimReportResource\Pages;
use App\Models\MapperClaimReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MapperClaimReportResource extends Resource
{
    protected static ?string $model = MapperClaimReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Creator Claim Disputes';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('mapper_claims') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = MapperClaimReport::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Details')
                    ->schema([
                        Forms\Components\Select::make('reporter_id')
                            ->relationship('reporter', 'plain_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('claim_info')
                            ->label('Disputed Claim')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->claim ? "\"{$record->claim->name}\" ({$record->claim->type}) by {$record->claim->user->plain_name}" : '-'),
                        Forms\Components\Textarea::make('reason')
                            ->disabled()
                            ->rows(3),
                    ]),
                Forms\Components\Section::make('Resolution')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'resolved' => 'Resolved - Claim removed',
                                'dismissed' => 'Dismissed - Claim is valid',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(3)
                            ->placeholder('Optional notes about the resolution...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'resolved' => 'success',
                        'dismissed' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reporter.plain_name')
                    ->label('Reported By')
                    ->searchable()
                    ->url(fn (MapperClaimReport $record) => route('profile.index', $record->reporter_id)),
                Tables\Columns\TextColumn::make('claim.name')
                    ->label('Claimed Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('claim.type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'map' => 'success',
                        'model' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('claim.user.plain_name')
                    ->label('Claimed By')
                    ->url(fn (MapperClaimReport $record) => $record->claim ? route('profile.index', $record->claim->user_id) : null),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(50)
                    ->tooltip(fn (MapperClaimReport $record) => $record->reason),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed',
                    ])
                    ->default(null),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('View / Resolve')
                    ->url(fn (MapperClaimReport $record) => static::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('remove_claim')
                    ->label('Remove Claim')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will delete the claim and mark the report as resolved.')
                    ->action(function (MapperClaimReport $record) {
                        $record->claim?->delete();
                        $record->update([
                            'status' => 'resolved',
                            'resolved_by' => auth()->id(),
                            'resolved_at' => now(),
                            'admin_notes' => ($record->admin_notes ? $record->admin_notes . "\n" : '') . 'Claim removed by admin.',
                        ]);
                    })
                    ->visible(fn (MapperClaimReport $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (MapperClaimReport $record) {
                        $record->update([
                            'status' => 'dismissed',
                            'resolved_by' => auth()->id(),
                            'resolved_at' => now(),
                        ]);
                    })
                    ->visible(fn (MapperClaimReport $record) => $record->status === 'pending'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMapperClaimReports::route('/'),
            'edit' => Pages\EditMapperClaimReport::route('/{record}/edit'),
        ];
    }
}
