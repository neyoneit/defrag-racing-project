<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RenderedVideoResource\Pages;
use App\Models\RenderedVideo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

class RenderedVideoResource extends Resource
{
    protected static ?string $model = RenderedVideo::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationLabel = 'Rendered Videos';

    protected static ?string $navigationGroup = 'Demome';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['record', 'demo', 'user']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Video Information')
                    ->schema([
                        Forms\Components\TextInput::make('map_name')
                            ->required(),
                        Forms\Components\TextInput::make('player_name')
                            ->required(),
                        Forms\Components\TextInput::make('physics'),
                        Forms\Components\TextInput::make('time_ms')
                            ->numeric(),
                        Forms\Components\TextInput::make('gametype'),
                    ])->columns(3),

                Forms\Components\Section::make('YouTube')
                    ->schema([
                        Forms\Components\TextInput::make('youtube_url')
                            ->url(),
                        Forms\Components\TextInput::make('youtube_video_id')
                            ->maxLength(20),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'rendering' => 'Rendering',
                                'uploading' => 'Uploading',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->required(),
                        Forms\Components\Select::make('priority')
                            ->options([
                                0 => '0 - User Request',
                                1 => '1 - World Record',
                                2 => '2 - Verified Record',
                                3 => '3 - Normal',
                            ]),
                        Forms\Components\Select::make('source')
                            ->options([
                                'discord' => 'Discord',
                                'web' => 'Web',
                                'auto' => 'Auto',
                            ]),
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible'),
                    ])->columns(2),

                Forms\Components\Section::make('YouTube Metadata (auto-generated)')
                    ->schema([
                        Forms\Components\TextInput::make('yt_title')
                            ->label('YouTube Title')
                            ->readOnly()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record) {
                                    $component->state(\App\Services\VideoMetadataService::generateTitle($record));
                                }
                            })
                            ->extraInputAttributes(['onclick' => 'this.select()', 'style' => 'font-family:monospace;'])
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('yt_description')
                            ->label('YouTube Description')
                            ->readOnly()
                            ->dehydrated(false)
                            ->rows(12)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record) {
                                    $component->state(\App\Services\VideoMetadataService::generateDescription($record));
                                }
                            })
                            ->extraInputAttributes(['onclick' => 'this.select()', 'style' => 'font-family:monospace;'])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('yt_tags')
                            ->label('YouTube Tags')
                            ->readOnly()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record) {
                                    $component->state(implode(', ', \App\Services\VideoMetadataService::generateTags($record)));
                                }
                            })
                            ->extraInputAttributes(['onclick' => 'this.select()', 'style' => 'font-family:monospace;'])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\TextInput::make('demo_url')
                            ->label('Demo URL')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('demo_filename')
                            ->label('Demo Filename'),
                        Forms\Components\TextInput::make('record_id')
                            ->numeric(),
                        Forms\Components\TextInput::make('demo_id')
                            ->numeric(),
                        Forms\Components\TextInput::make('requested_by'),
                        Forms\Components\TextInput::make('render_duration_seconds')
                            ->numeric(),
                        Forms\Components\TextInput::make('video_file_size')
                            ->numeric(),
                        Forms\Components\Textarea::make('failure_reason')
                            ->rows(2),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('map_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('player_name')
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : '-'),

                Tables\Columns\TextColumn::make('physics')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'cpm' ? 'info' : 'success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'rendering',
                        'primary' => 'uploading',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'primary' => 'upload_pending',
                    ]),

                Tables\Columns\BadgeColumn::make('source')
                    ->colors([
                        'info' => 'discord',
                        'success' => 'web',
                        'gray' => 'auto',
                    ]),

                Tables\Columns\TextColumn::make('priority')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => match($state) {
                        -1 => 'Force',
                        0 => 'User',
                        1 => 'Auto',
                        default => (string) $state,
                    }),

                Tables\Columns\TextColumn::make('quality_tier')
                    ->label('Tier')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state ? (\App\Services\RenderQueueService::TIER_LABELS[$state] ?? '?') : '-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('time_ms')
                    ->label('Time')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        $m = floor($state / 60000);
                        $s = floor(($state % 60000) / 1000);
                        $ms = $state % 1000;
                        return sprintf('%d:%02d.%03d', $m, $s, $ms);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('youtube_url')
                    ->label('YT')
                    ->url(fn ($record) => $record->youtube_url)
                    ->openUrlInNewTab()
                    ->limit(10)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('requested_by')
                    ->searchable()
                    ->toggleable()
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : '-'),

                Tables\Columns\TextColumn::make('render_duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state ? gmdate('H:i:s', $state) : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Queued')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Completed')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->visible(fn ($record) => true)
                    ->formatStateUsing(fn ($record) => $record->status === 'completed' ? $record->updated_at->format('Y-m-d H:i') : '-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'rendering' => 'Rendering',
                        'uploading' => 'Uploading',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'upload_pending' => 'Upload Pending',
                    ]),
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'discord' => 'Discord',
                        'web' => 'Web',
                        'auto' => 'Auto',
                        'community_tasks' => 'Community Tasks',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        0 => 'User Request',
                        1 => 'World Record',
                        2 => 'Verified Record',
                        3 => 'Normal',
                    ]),
                Tables\Filters\Filter::make('wr_online')
                    ->label('WR with online demo')
                    ->query(function (Builder $query) {
                        $query->whereHas('record', function ($q) {
                            $q->where('rank', 1)->whereNull('deleted_at');
                        })->whereHas('demo');
                    }),
                Tables\Filters\Filter::make('wr_or_faster_offline')
                    ->label('WR or faster (offline demo)')
                    ->query(function (Builder $query) {
                        $query->whereHas('demo', function ($dq) {
                            $dq->whereNull('record_id')->orWhereDoesntHave('record');
                        })->whereRaw('time_ms <= (SELECT MIN(r.time) FROM records r WHERE r.mapname = rendered_videos.map_name AND r.physics = rendered_videos.physics AND r.deleted_at IS NULL)');
                    }),
                Tables\Filters\SelectFilter::make('physics')
                    ->options([
                        'vq3' => 'VQ3',
                        'cpm' => 'CPM',
                    ]),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\Action::make('copyYoutubeInfo')
                    ->label('YT Info')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->modalHeading('YouTube Title & Description')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (RenderedVideo $record) {
                        $title = \App\Services\VideoMetadataService::generateTitle($record);
                        $description = \App\Services\VideoMetadataService::generateDescription($record);
                        $tags = implode(', ', \App\Services\VideoMetadataService::generateTags($record));

                        $inputStyle = 'width:100%;background:#1f2937;border:1px solid #4b5563;border-radius:6px;padding:8px 12px;font-size:13px;color:#e5e7eb;font-family:monospace;';

                        return new \Illuminate\Support\HtmlString("
                            <div style='display:flex;flex-direction:column;gap:12px;'>
                                <div>
                                    <label style='font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;'>Title</label>
                                    <input type='text' value='" . htmlspecialchars($title, ENT_QUOTES) . "' style='{$inputStyle}' onclick='this.select()' readonly />
                                </div>
                                <div>
                                    <label style='font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;'>Description</label>
                                    <textarea style='{$inputStyle}' rows='12' onclick='this.select()' readonly>" . htmlspecialchars($description, ENT_QUOTES) . "</textarea>
                                </div>
                                <div>
                                    <label style='font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;'>Tags</label>
                                    <input type='text' value='" . htmlspecialchars($tags, ENT_QUOTES) . "' style='{$inputStyle}' onclick='this.select()' readonly />
                                </div>
                            </div>
                        ");
                    }),
                Tables\Actions\Action::make('retry_upload')
                    ->label('Retry Upload')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('info')
                    ->visible(fn (RenderedVideo $record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->modalHeading('Retry upload for this video?')
                    ->modalDescription('The rendered file must still exist on the demome PC. This will skip rendering and only retry the YouTube upload.')
                    ->action(function (RenderedVideo $record) {
                        $record->update([
                            'status' => 'upload_pending',
                            'failure_reason' => null,
                        ]);

                        Notification::make()
                            ->title('Queued for re-upload')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('rerender')
                    ->label('Re-render')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (RenderedVideo $record) => $record->status === 'completed' || $record->status === 'failed')
                    ->requiresConfirmation()
                    ->modalHeading('Re-render this video?')
                    ->modalDescription('This will reset the video to pending and queue it for re-rendering.')
                    ->action(function (RenderedVideo $record) {
                        $demoUrl = $record->demo_url;
                        if ($record->demo_id) {
                            $demoUrl = config('app.url') . "/api/demome/download-demo/{$record->demo_id}";
                        }

                        $record->update([
                            'status' => 'pending',
                            'youtube_url' => null,
                            'youtube_video_id' => null,
                            'render_duration_seconds' => null,
                            'video_file_size' => null,
                            'failure_reason' => null,
                            'retry_count' => 0,
                            'demo_url' => $demoUrl,
                        ]);

                        Notification::make()
                            ->title('Queued for re-render')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('force_render')
                    ->label('Force render')
                    ->icon('heroicon-o-bolt')
                    ->color('danger')
                    ->visible(fn (RenderedVideo $record) => in_array($record->status, ['pending', 'failed']))
                    ->requiresConfirmation()
                    ->modalHeading('Force render this video?')
                    ->modalDescription('This will set highest priority (-1) and bypass pause. Demome will pick it up on next poll.')
                    ->action(function (RenderedVideo $record) {
                        $demoUrl = $record->demo_url;
                        if ($record->demo_id) {
                            $demoUrl = config('app.url') . "/api/demome/download-demo/{$record->demo_id}";
                        }

                        $record->update([
                            'status' => 'pending',
                            'priority' => -1,
                            'youtube_url' => null,
                            'youtube_video_id' => null,
                            'render_duration_seconds' => null,
                            'video_file_size' => null,
                            'failure_reason' => null,
                            'retry_count' => 0,
                            'demo_url' => $demoUrl,
                        ]);

                        Notification::make()
                            ->title('Force render queued')
                            ->body('Will bypass pause and render next.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_force_render')
                        ->label('Force render selected')
                        ->icon('heroicon-o-bolt')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!in_array($record->status, ['pending', 'failed'])) continue;
                                $demoUrl = $record->demo_url;
                                if ($record->demo_id) {
                                    $demoUrl = config('app.url') . "/api/demome/download-demo/{$record->demo_id}";
                                }
                                $record->update([
                                    'status' => 'pending',
                                    'priority' => -1,
                                    'demo_url' => $demoUrl,
                                    'failure_reason' => null,
                                    'retry_count' => 0,
                                ]);
                                $count++;
                            }
                            Notification::make()
                                ->title("Force render queued: {$count} videos")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_queue')
                        ->label('Queue selected (normal)')
                        ->icon('heroicon-o-queue-list')
                        ->color('info')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status !== 'failed') continue;
                                $demoUrl = $record->demo_url;
                                if ($record->demo_id) {
                                    $demoUrl = config('app.url') . "/api/demome/download-demo/{$record->demo_id}";
                                }
                                $record->update([
                                    'status' => 'pending',
                                    'priority' => 0,
                                    'demo_url' => $demoUrl,
                                    'failure_reason' => null,
                                    'retry_count' => 0,
                                ]);
                                $count++;
                            }
                            Notification::make()
                                ->title("Re-queued: {$count} videos")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRenderedVideos::route('/'),
            'edit' => Pages\EditRenderedVideo::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return \Illuminate\Support\Facades\Cache::remember('rendered_videos:pending_count', 60, function () {
            return static::getModel()::where('status', 'pending')->count() ?: null;
        });
    }
}
