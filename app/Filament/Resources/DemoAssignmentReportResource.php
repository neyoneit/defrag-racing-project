<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DemoAssignmentReportResource\Pages;
use App\Models\DemoAssignmentReport;
use App\Models\UploadedDemo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class DemoAssignmentReportResource extends Resource
{
    protected static ?string $model = DemoAssignmentReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Demo Reports';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'demo',
                'currentRecord',
                'currentRecord.user',
                'suggestedRecord',
                'suggestedRecord.user',
                'reporter',
                'resolver'
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Information')
                    ->schema([
                        Forms\Components\Select::make('report_type')
                            ->label('Report Type')
                            ->options([
                                'reassignment_request' => 'Reassignment Request',
                                'wrong_assignment' => 'Wrong Assignment',
                                'bad_demo' => 'Bad Demo',
                            ])
                            ->required()
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'resolved' => 'Resolved',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('reason_type')
                            ->label('Reason Type')
                            ->disabled(),

                        Forms\Components\Textarea::make('reason_details')
                            ->label('Reporter Details')
                            ->disabled()
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Section::make('Demo Information')
                    ->schema([
                        Forms\Components\TextInput::make('demo.original_filename')
                            ->label('Original Filename')
                            ->disabled()
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('demo.map_name')
                            ->label('Map')
                            ->disabled()
                            ->default('TEST MAP'),

                        Forms\Components\TextInput::make('demo.physics')
                            ->label('Physics')
                            ->disabled()
                            ->default('TEST PHYSICS'),

                        Forms\Components\TextInput::make('demo.time_ms')
                            ->label('Time')
                            ->disabled()
                            ->default('TEST TIME')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ms' : 'TEST'),

                        Forms\Components\TextInput::make('demo.player_name')
                            ->label('Player Name in Demo')
                            ->disabled(),

                        Forms\Components\TextInput::make('demo.status')
                            ->label('Demo Status')
                            ->disabled(),

                        Forms\Components\Placeholder::make('demo_download')
                            ->label('Download Demo')
                            ->content(fn ($record) => $record->demo?->file_path
                                ? new \Illuminate\Support\HtmlString(
                                    '<a href="' . $record->demo->file_path . '" target="_blank" class="text-primary-600 hover:underline font-semibold">
                                        Download Demo File
                                    </a>'
                                )
                                : 'Demo file not available'
                            ),
                    ])->columns(3),

                Forms\Components\Section::make('Current Assignment')
                    ->schema([
                        Forms\Components\TextInput::make('demo.record_id')
                            ->label('Current Record ID')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ?: 'Not assigned'),

                        Forms\Components\TextInput::make('currentRecord.user.name')
                            ->label('Current Player')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ?: 'N/A'),

                        Forms\Components\TextInput::make('currentRecord.time')
                            ->label('Record Time')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ms' : 'N/A'),

                        Forms\Components\TextInput::make('currentRecord.date_set')
                            ->label('Record Date')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i') : 'N/A'),
                    ])->columns(4)
                    ->visible(fn ($record) => $record->demo?->record_id !== null),

                Forms\Components\Section::make('Suggested Assignment')
                    ->schema([
                        Forms\Components\TextInput::make('suggested_record_id')
                            ->label('Suggested Record ID')
                            ->disabled(),

                        Forms\Components\TextInput::make('suggestedRecord.user.name')
                            ->label('Suggested Player')
                            ->disabled(),

                        Forms\Components\TextInput::make('suggestedRecord.time')
                            ->label('Record Time')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ms' : 'N/A'),

                        Forms\Components\TextInput::make('suggestedRecord.date_set')
                            ->label('Record Date')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i') : 'N/A'),
                    ])->columns(4)
                    ->visible(fn ($record) => $record->suggested_record_id !== null),

                Forms\Components\Section::make('Reporter Information')
                    ->schema([
                        Forms\Components\TextInput::make('reporter.name')
                            ->label('Reported By')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Report Date')
                            ->disabled(),
                    ])->columns(2),

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

                Tables\Columns\BadgeColumn::make('report_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'reassignment_request',
                        'warning' => 'wrong_assignment',
                        'danger' => 'bad_demo',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'reassignment_request' => 'Reassign',
                        'wrong_assignment' => 'Wrong',
                        'bad_demo' => 'Bad Demo',
                    }),

                Tables\Columns\TextColumn::make('demo.processed_filename')
                    ->label('Demo File')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->demo?->processed_filename),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : 'N/A'),

                Tables\Columns\TextColumn::make('currentRecord.name')
                    ->label('Current Player')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : '-'),

                Tables\Columns\TextColumn::make('suggestedRecord.name')
                    ->label('Suggested Player')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : '-'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'secondary' => 'resolved',
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
                        'resolved' => 'Resolved',
                    ])
                    ->default('pending'),

                Tables\Filters\SelectFilter::make('report_type')
                    ->label('Type')
                    ->options([
                        'reassignment_request' => 'Reassignment Request',
                        'wrong_assignment' => 'Wrong Assignment',
                        'bad_demo' => 'Bad Demo',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DemoAssignmentReport $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Report')
                    ->modalDescription(fn (DemoAssignmentReport $record) =>
                        "Are you sure you want to approve this {$record->report_type} report?"
                    )
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes (Optional)')
                            ->rows(3),
                    ])
                    ->action(function (DemoAssignmentReport $record, array $data) {
                        $record->update([
                            'status' => 'approved',
                            'resolved_at' => now(),
                            'resolved_by_admin_id' => auth()->id(),
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);

                        // Handle reassignment if it's a reassignment request
                        if ($record->report_type === 'reassignment_request' && $record->suggested_record_id) {
                            $demo = UploadedDemo::find($record->demo_id);
                            if ($demo) {
                                $demo->update([
                                    'record_id' => $record->suggested_record_id,
                                    'manually_assigned' => true,
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('Report Approved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DemoAssignmentReport $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Report')
                    ->modalDescription('Please provide a reason for rejecting this report.')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (DemoAssignmentReport $record, array $data) {
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

                Tables\Actions\Action::make('revert')
                    ->label('Revert to Pending')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (DemoAssignmentReport $record) => in_array($record->status, ['approved', 'rejected']))
                    ->requiresConfirmation()
                    ->modalHeading('Revert Report to Pending')
                    ->modalDescription('This will reset the report status to pending and clear resolution data.')
                    ->action(function (DemoAssignmentReport $record) {
                        $record->update([
                            'status' => 'pending',
                            'resolved_at' => null,
                            'resolved_by_admin_id' => null,
                            'admin_notes' => null,
                        ]);

                        Notification::make()
                            ->title('Report Reverted to Pending')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Report')
                    ->modalDescription('Are you sure you want to delete this report? This action cannot be undone.'),
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
            'index' => Pages\ListDemoAssignmentReports::route('/'),
            'create' => Pages\CreateDemoAssignmentReport::route('/create'),
            'edit' => Pages\EditDemoAssignmentReport::route('/{record}/edit'),
            'view' => Pages\ViewDemoAssignmentReport::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
