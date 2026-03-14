<?php

namespace App\Filament\Resources\DemoAssignmentReportResource\Pages;

use App\Filament\Resources\DemoAssignmentReportResource;
use App\Filament\Resources\UserResource;
use App\Models\UploadedDemo;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewDemoAssignmentReport extends ViewRecord
{
    protected static string $resource = DemoAssignmentReportResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Report Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('report_type')
                            ->label('Report Type')
                            ->formatStateUsing(fn (string $state): string => match($state) {
                                'reassignment_request' => 'Reassignment Request',
                                'wrong_assignment' => 'Wrong Assignment',
                                'bad_demo' => 'Bad Demo',
                                default => $state,
                            })
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'reassignment_request' => 'primary',
                                'wrong_assignment' => 'warning',
                                'bad_demo' => 'danger',
                                default => 'secondary',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'secondary',
                            }),
                        Infolists\Components\TextEntry::make('reason_type')
                            ->label('Reason Type'),
                        Infolists\Components\TextEntry::make('reason_details')
                            ->label('Reporter Details')
                            ->columnSpanFull(),
                    ])->columns(3),

                Infolists\Components\Section::make('Demo Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('demo.original_filename')
                            ->label('Original Filename')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('demo.map_name')
                            ->label('Map'),
                        Infolists\Components\TextEntry::make('demo.physics')
                            ->label('Physics'),
                        Infolists\Components\TextEntry::make('demo.time_ms')
                            ->label('Time')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ms' : 'N/A'),
                        Infolists\Components\TextEntry::make('demo.player_name')
                            ->label('Player Name in Demo'),
                        Infolists\Components\TextEntry::make('demo.status')
                            ->label('Demo Status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('demo.file_path')
                            ->label('Download Demo')
                            ->formatStateUsing(fn ($state) => $state ? 'Download' : 'Not available')
                            ->url(fn ($record) => $record->demo?->file_path)
                            ->openUrlInNewTab()
                            ->color('primary'),
                    ])->columns(3),

                Infolists\Components\Section::make('Current Assignment')
                    ->schema([
                        Infolists\Components\TextEntry::make('demo.record_id')
                            ->label('Current Record ID'),
                        Infolists\Components\TextEntry::make('currentRecord.name')
                            ->label('Current Player')
                            ->html()
                            ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : 'N/A'),
                        Infolists\Components\TextEntry::make('currentRecord.time')
                            ->label('Record Time')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ms' : 'N/A'),
                        Infolists\Components\TextEntry::make('currentRecord.date_set')
                            ->label('Record Date')
                            ->dateTime('Y-m-d H:i'),
                    ])->columns(4)
                    ->visible(fn ($record) => $record->demo?->record_id !== null),

                Infolists\Components\Section::make('Suggested Assignment')
                    ->schema([
                        Infolists\Components\TextEntry::make('suggested_record_id')
                            ->label('Suggested Record ID'),
                        Infolists\Components\TextEntry::make('suggestedRecord.name')
                            ->label('Suggested Player')
                            ->html()
                            ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : 'N/A'),
                        Infolists\Components\TextEntry::make('suggestedRecord.time')
                            ->label('Record Time')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ms' : 'N/A'),
                        Infolists\Components\TextEntry::make('suggestedRecord.date_set')
                            ->label('Record Date')
                            ->dateTime('Y-m-d H:i'),
                    ])->columns(4)
                    ->visible(fn ($record) => $record->suggested_record_id !== null),

                Infolists\Components\Section::make('Reporter Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('reporter.name')
                            ->label('Reported By')
                            ->html()
                            ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : 'N/A'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Report Date')
                            ->dateTime(),
                    ])->columns(2),

                Infolists\Components\Section::make('Admin Actions')
                    ->schema([
                        Infolists\Components\TextEntry::make('admin_notes')
                            ->label('Admin Notes')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('resolved_at')
                            ->label('Resolved At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('resolver.name')
                            ->label('Resolved By'),
                    ])->columns(2)
                    ->visible(fn ($record) => $record->resolved_at !== null),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('approve')
                ->label('Approve Report')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Approve Report')
                ->modalDescription(fn () =>
                    "Are you sure you want to approve this {$this->record->report_type} report?"
                )
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Admin Notes (Optional)')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'approved',
                        'resolved_at' => now(),
                        'resolved_by_admin_id' => auth()->id(),
                        'admin_notes' => $data['admin_notes'] ?? null,
                    ]);

                    // Handle reassignment if it's a reassignment request
                    if ($this->record->report_type === 'reassignment_request' && $this->record->suggested_record_id) {
                        $demo = UploadedDemo::find($this->record->demo_id);
                        if ($demo) {
                            $demo->update([
                                'record_id' => $this->record->suggested_record_id,
                                'manually_assigned' => true,
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Report Approved')
                        ->success()
                        ->send();

                    return redirect()->route('filament.admin.resources.demo-assignment-reports.index');
                }),

            Actions\Action::make('reject')
                ->label('Reject Report')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Reject Report')
                ->modalDescription('Please provide a reason for rejecting this report.')
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'rejected',
                        'resolved_at' => now(),
                        'resolved_by_admin_id' => auth()->id(),
                        'admin_notes' => $data['admin_notes'],
                    ]);

                    Notification::make()
                        ->title('Report Rejected')
                        ->success()
                        ->send();

                    return redirect()->route('filament.admin.resources.demo-assignment-reports.index');
                }),
        ];
    }
}
