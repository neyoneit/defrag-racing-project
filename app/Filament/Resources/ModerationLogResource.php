<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModerationLogResource\Pages;
use App\Models\ModerationLog;
use App\Models\Tag;
use App\Models\TagActivity;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ModerationLogResource extends Resource
{
    protected static ?string $model = ModerationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Mod Logs';

    protected static ?string $pluralModelLabel = 'Moderation Logs';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->is_moderator ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->label('When'),
                Tables\Columns\TextColumn::make('user.plain_name')
                    ->label('Who')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => \App\Filament\Resources\UserResource::q3tohtml($state))
                    ->html(),
                Tables\Columns\TextColumn::make('area')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tags' => 'primary',
                        'mapper_claims' => 'info',
                        'record_flags' => 'danger',
                        'models' => 'warning',
                        'alias_reports' => 'success',
                        'demo_assignments' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'resolved', 'restored', 'claim_removed' => 'success',
                        'rejected', 'dismissed', 'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('metadata')
                    ->label('Details')
                    ->getStateUsing(function (ModerationLog $record) {
                        $meta = $record->metadata ?? [];
                        $parts = [];
                        foreach (['claim_name', 'claim_user', 'reporter', 'reason', 'model_name', 'flag_reason'] as $key) {
                            if (isset($meta[$key])) $parts[] = "{$key}: {$meta[$key]}";
                        }
                        return implode(', ', $parts) ?: '-';
                    })
                    ->limit(80)
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('restore_tag')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->label('Restore')
                    ->visible(fn (ModerationLog $record) => $record->area === 'tags' && $record->action === 'deleted' && !($record->metadata['restored'] ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Restore deleted tag?')
                    ->modalDescription(function (ModerationLog $record) {
                        $meta = $record->metadata ?? [];
                        $name = $meta['tag_name'] ?? '?';
                        return "This will recreate tag \"{$name}\" and re-attach it to its maps.";
                    })
                    ->action(function (ModerationLog $record): void {
                        // Find matching TagActivity record to get full metadata (map_ids etc.)
                        $tagActivity = TagActivity::where('action', 'deleted')
                            ->whereNotNull('metadata')
                            ->whereRaw("JSON_EXTRACT(metadata, '$.tag_name') = ?", [$record->metadata['tag_name'] ?? ''])
                            ->where('user_id', $record->user_id)
                            ->latest()
                            ->first();

                        if (!$tagActivity) {
                            Notification::make()->title('No matching tag activity found for restore')->danger()->send();
                            return;
                        }

                        $meta = $tagActivity->metadata ?? [];
                        $name = $meta['tag_name'] ?? null;
                        $normalized = $meta['tag_name_normalized'] ?? strtolower($name ?? '');

                        if (!$name) {
                            Notification::make()->title('Cannot restore - no tag name')->danger()->send();
                            return;
                        }

                        $existing = Tag::where('name', $normalized)->first();
                        if ($existing) {
                            Notification::make()->title("Tag \"{$name}\" already exists (ID: {$existing->id})")->danger()->send();
                            return;
                        }

                        $tag = Tag::create([
                            'name' => $normalized,
                            'display_name' => $name,
                            'category' => $meta['category'] ?? null,
                            'note' => $meta['note'] ?? null,
                            'blocked_keywords' => $meta['blocked_keywords'] ?? null,
                            'youtube_url' => $meta['youtube_url'] ?? null,
                            'parent_tag_id' => $meta['parent_tag_id'] ?? null,
                            'usage_count' => 0,
                        ]);

                        $mapIds = $meta['map_ids'] ?? [];
                        $reattached = 0;
                        foreach ($mapIds as $mapId) {
                            if (\App\Models\Map::where('id', $mapId)->exists()) {
                                DB::table('map_tag')->insertOrIgnore([
                                    'map_id' => $mapId,
                                    'tag_id' => $tag->id,
                                    'user_id' => auth()->id(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                $reattached++;
                            }
                        }

                        $maplistIds = $meta['maplist_ids'] ?? [];
                        foreach ($maplistIds as $maplistId) {
                            if (\App\Models\Maplist::where('id', $maplistId)->exists()) {
                                DB::table('maplist_tag')->insertOrIgnore([
                                    'maplist_id' => $maplistId,
                                    'tag_id' => $tag->id,
                                    'user_id' => auth()->id(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }

                        $tag->update([
                            'usage_count' => DB::table('map_tag')->where('tag_id', $tag->id)->count()
                                + DB::table('maplist_tag')->where('tag_id', $tag->id)->count(),
                        ]);

                        // Mark both logs as restored
                        $record->update(['metadata' => array_merge($record->metadata ?? [], ['restored' => true])]);
                        $tagActivity->update(['tag_id' => $tag->id, 'metadata' => array_merge($tagActivity->metadata ?? [], ['restored' => true, 'restored_by' => auth()->id()])]);

                        ModerationLog::log('tags', 'restored', $tag, ['tag_name' => $name, 'maps_restored' => $reattached]);

                        Notification::make()
                            ->title("Tag \"{$name}\" restored with {$reattached} maps")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('revert')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->label('Revert')
                    ->visible(fn (ModerationLog $record) => in_array($record->action, ['approved', 'rejected', 'dismissed', 'claim_removed']) && !($record->metadata['reverted'] ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Revert this action?')
                    ->modalDescription('This will attempt to undo this moderation action.')
                    ->action(function (ModerationLog $record): void {
                        // Generic revert - reset status on subject back to pending
                        $subject = $record->subject;
                        if (!$subject) {
                            Notification::make()->title('Subject no longer exists')->danger()->send();
                            return;
                        }

                        if (method_exists($subject, 'getFillable') && in_array('status', $subject->getFillable())) {
                            $subject->update([
                                'status' => 'pending',
                                'resolved_at' => null,
                                'resolved_by_admin_id' => null,
                            ]);
                        } elseif (isset($subject->approval_status)) {
                            $subject->update(['approval_status' => 'pending']);
                        }

                        $record->update(['metadata' => array_merge($record->metadata ?? [], ['reverted' => true, 'reverted_by' => auth()->id()])]);

                        ModerationLog::log($record->area, 'reverted', $subject, [
                            'original_action' => $record->action,
                            'original_log_id' => $record->id,
                        ]);

                        Notification::make()->title('Action reverted to pending')->success()->send();
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('area')
                    ->options([
                        'tags' => 'Tags',
                        'mapper_claims' => 'Creator Claims',
                        'record_flags' => 'Record Flags',
                        'models' => 'Models',
                        'alias_reports' => 'Alias Reports',
                        'demo_assignments' => 'Demo Assignments',
                    ]),
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                        'merged' => 'Merged',
                        'hidden' => 'Hidden',
                        'shown' => 'Shown',
                        'claim_removed' => 'Claim Removed',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModerationLogs::route('/'),
        ];
    }
}
