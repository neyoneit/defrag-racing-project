<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AliasReportResource\Pages;
use App\Models\AliasReport;
use App\Models\UserAlias;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class AliasReportResource extends Resource
{
    protected static ?string $model = AliasReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Alias Reports';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Information')
                    ->schema([
                        Forms\Components\Select::make('alias_id')
                            ->label('Reported Alias')
                            ->relationship('alias', 'alias')
                            ->searchable()
                            ->required()
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('reason')
                            ->label('Report Reason')
                            ->disabled()
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Section::make('Reporter Information')
                    ->schema([
                        Forms\Components\TextInput::make('reporter.name')
                            ->label('Reported By')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Report Date')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Alias Details')
                    ->schema([
                        Forms\Components\TextInput::make('alias.alias')
                            ->label('Alias Name')
                            ->disabled(),

                        Forms\Components\TextInput::make('alias.user.name')
                            ->label('Alias Owner')
                            ->disabled(),

                        Forms\Components\Toggle::make('alias.is_approved')
                            ->label('Alias Approved')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Admin Actions')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->placeholder('Add notes about this report resolution...'),

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
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('alias.alias')
                    ->label('Reported Alias')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('alias.user.name')
                    ->label('Alias Owner')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('alias.is_approved')
                    ->label('Alias Approved')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reason),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),

                Tables\Filters\TernaryFilter::make('alias.is_approved')
                    ->label('Alias Approved')
                    ->placeholder('All aliases')
                    ->trueLabel('Approved aliases only')
                    ->falseLabel('Unapproved aliases only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('approve_report')
                    ->label('Approve & Reject Alias')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (AliasReport $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Report & Reject Alias')
                    ->modalDescription(fn (AliasReport $record) =>
                        "This will approve the report and mark alias '{$record->alias->alias}' as unapproved."
                    )
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes (Optional)')
                            ->rows(3),
                    ])
                    ->action(function (AliasReport $record, array $data) {
                        // Approve the report
                        $record->update([
                            'status' => 'approved',
                            'resolved_at' => now(),
                            'resolved_by_admin_id' => auth()->id(),
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);

                        // Reject the alias
                        $alias = UserAlias::find($record->alias_id);
                        if ($alias) {
                            $alias->update(['is_approved' => false]);
                        }

                        Notification::make()
                            ->title('Report Approved & Alias Rejected')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject_report')
                    ->label('Reject Report')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (AliasReport $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Report')
                    ->modalDescription('The report is invalid. The alias will remain as-is.')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (AliasReport $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'resolved_at' => now(),
                            'resolved_by_admin_id' => auth()->id(),
                            'admin_notes' => $data['admin_notes'],
                        ]);

                        Notification::make()
                            ->title('Report Rejected')
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListAliasReports::route('/'),
            'create' => Pages\CreateAliasReport::route('/create'),
            'edit' => Pages\EditAliasReport::route('/{record}/edit'),
            'view' => Pages\ViewAliasReport::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
