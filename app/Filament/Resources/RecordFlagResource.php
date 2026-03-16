<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecordFlagResource\Pages;
use App\Models\RecordFlag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class RecordFlagResource extends Resource
{
    protected static ?string $model = RecordFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Record Flags';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['record', 'record.user', 'demo', 'flagger', 'resolver']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Flag Information')
                    ->schema([
                        Forms\Components\TextInput::make('flag_type')
                            ->label('Flag Type')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('note')
                            ->label('User Note')
                            ->disabled()
                            ->rows(2),
                    ])->columns(2),

                Forms\Components\Section::make('Target')
                    ->schema([
                        Forms\Components\TextInput::make('record_id')
                            ->label('Record ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('record.user.name')
                            ->label('Record Player')
                            ->disabled(),

                        Forms\Components\TextInput::make('demo_id')
                            ->label('Demo ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('demo.original_filename')
                            ->label('Demo File')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Flagged By')
                    ->schema([
                        Forms\Components\TextInput::make('flagger.name')
                            ->label('User')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Flagged At')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Admin Actions')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->placeholder('Add notes about this flag resolution...')
                            ->helperText(fn () => new \Illuminate\Support\HtmlString(
                                'Your name will be added automatically: [' . UserResource::q3tohtml(auth()->user()?->name ?? '') . ']'
                            )),

                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Resolved At')
                            ->disabled(),

                        Forms\Components\TextInput::make('resolver.name')
                            ->label('Resolved By')
                            ->disabled(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('flag_type')
                    ->label('Flag')
                    ->colors([
                        'danger' => fn (string $state): bool => in_array($state, ['sv_cheats', 'tool_assisted']),
                        'warning' => fn (string $state): bool => !in_array($state, ['sv_cheats', 'tool_assisted']),
                    ]),

                Tables\Columns\TextColumn::make('record_id')
                    ->label('Record')
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? "#{$state}" . ($record->record?->user?->name ? " ({$record->record->user->name})" : '')
                        : '-'
                    )
                    ->searchable(),

                Tables\Columns\TextColumn::make('demo.original_filename')
                    ->label('Demo')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->demo?->original_filename)
                    ->searchable(),

                Tables\Columns\TextColumn::make('flag_count')
                    ->label('Users')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state >= 3 ? 'danger' : ($state >= 2 ? 'warning' : 'gray')),

                Tables\Columns\TextColumn::make('flagger.name')
                    ->label('First Flagged By')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : 'N/A'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Flagged')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default(null),

                Tables\Filters\SelectFilter::make('flag_type')
                    ->label('Flag Type')
                    ->options([
                        'sv_cheats' => 'sv_cheats',
                        'tool_assisted' => 'TAS',
                        'client_finish' => 'No finish',
                        'timescale' => 'Timescale',
                        'g_speed' => 'Speed',
                        'g_gravity' => 'Gravity',
                        'sv_fps' => 'FPS',
                        'com_maxfps' => 'Max FPS',
                        'pmove_fixed' => 'pmove',
                        'pmove_msec' => 'msec',
                        'df_mp_interferenceoff' => 'Interference',
                        'other' => 'Other',
                    ]),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RecordFlag $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Flag')
                    ->modalDescription(fn (RecordFlag $record) =>
                        "Approve this {$record->flag_type} flag?"
                    )
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes (Optional)')
                            ->rows(3),
                    ])
                    ->action(function (RecordFlag $record, array $data) {
                        $adminName = auth()->user()->name;
                        $notes = $data['admin_notes'] ?? null;
                        if ($notes) {
                            $notes = "[{$adminName} - " . now()->format('Y-m-d H:i') . "] {$notes}";
                        }

                        $record->update([
                            'status' => 'approved',
                            'resolved_at' => now(),
                            'resolved_by_admin_id' => auth()->id(),
                            'admin_notes' => $notes,
                        ]);

                        Notification::make()
                            ->title('Flag Approved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (RecordFlag $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Flag')
                    ->modalDescription('Please provide a reason for rejecting this flag.')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (RecordFlag $record, array $data) {
                        $adminName = auth()->user()->name;
                        $notes = "[{$adminName} - " . now()->format('Y-m-d H:i') . "] {$data['admin_notes']}";

                        $record->update([
                            'status' => 'rejected',
                            'resolved_at' => now(),
                            'resolved_by_admin_id' => auth()->id(),
                            'admin_notes' => $notes,
                        ]);

                        Notification::make()
                            ->title('Flag Rejected')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('revert')
                    ->label('Revert to Pending')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (RecordFlag $record) => in_array($record->status, ['approved', 'rejected']))
                    ->requiresConfirmation()
                    ->action(function (RecordFlag $record) {
                        $record->update([
                            'status' => 'pending',
                            'resolved_at' => null,
                            'resolved_by_admin_id' => null,
                            'admin_notes' => null,
                        ]);

                        Notification::make()
                            ->title('Flag Reverted to Pending')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecordFlags::route('/'),
            'edit' => Pages\EditRecordFlag::route('/{record}/edit'),
            'view' => Pages\ViewRecordFlag::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
