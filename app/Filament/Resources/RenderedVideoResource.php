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
                        0 => 'User',
                        1 => 'WR',
                        2 => 'Verified',
                        default => 'Normal',
                    }),

                Tables\Columns\TextColumn::make('youtube_url')
                    ->label('YouTube')
                    ->url(fn ($record) => $record->youtube_url)
                    ->openUrlInNewTab()
                    ->limit(20)
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
                    ->dateTime()
                    ->sortable()
                    ->since(),
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
                    ]),
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'discord' => 'Discord',
                        'web' => 'Web',
                        'auto' => 'Auto',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        0 => 'User Request',
                        1 => 'World Record',
                        2 => 'Verified Record',
                        3 => 'Normal',
                    ]),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
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
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
