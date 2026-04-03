<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModerationLogResource\Pages;
use App\Models\MapperClaim;
use App\Models\ModerationLog;
use App\Models\Tag;
use App\Models\TagActivity;
use Filament\Forms;
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

    protected static ?int $navigationSort = 99;

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
                        'approved', 'resolved', 'restored', 'claim_removed', 'tag_unbanned' => 'success',
                        'rejected', 'dismissed', 'deleted', 'tag_banned' => 'danger',
                        'reverted' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('metadata')
                    ->label('Details')
                    ->getStateUsing(function (ModerationLog $record) {
                        $meta = $record->metadata ?? [];
                        $parts = [];
                        foreach (['tag_name', 'source_tag', 'target_tag', 'result', 'original_user', 'maps_restored', 'banned_user', 'claim_name', 'claim_user', 'reporter', 'reason', 'model_name', 'flag_reason'] as $key) {
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
                        $logMeta = $record->metadata ?? [];
                        $name = $logMeta['tag_name'] ?? null;

                        if (!$name) {
                            Notification::make()->title('Cannot restore - no tag name in log metadata')->danger()->send();
                            return;
                        }

                        // Use metadata from ModerationLog directly (new format has map_ids)
                        // Fall back to TagActivity for older logs that don't have map_ids
                        $meta = $logMeta;
                        $tagActivity = null;

                        if (!isset($meta['map_ids'])) {
                            $tagActivity = TagActivity::where('action', 'deleted')
                                ->whereNotNull('metadata')
                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.tag_name')) = ?", [$name])
                                ->where('user_id', $record->user_id)
                                ->latest()
                                ->first();

                            if ($tagActivity) {
                                $meta = array_merge($meta, $tagActivity->metadata ?? []);
                            }
                        }

                        $normalized = $meta['tag_name_normalized'] ?? strtolower($name);

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
                        $maplistReattached = 0;
                        foreach ($maplistIds as $maplistId) {
                            if (\App\Models\Maplist::where('id', $maplistId)->exists()) {
                                DB::table('maplist_tag')->insertOrIgnore([
                                    'maplist_id' => $maplistId,
                                    'tag_id' => $tag->id,
                                    'user_id' => auth()->id(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                $maplistReattached++;
                            }
                        }

                        $tag->update([
                            'usage_count' => DB::table('map_tag')->where('tag_id', $tag->id)->count()
                                + DB::table('maplist_tag')->where('tag_id', $tag->id)->count(),
                        ]);

                        // Mark log as restored
                        $record->update(['metadata' => array_merge($logMeta, ['restored' => true])]);
                        if ($tagActivity) {
                            $tagActivity->update(['tag_id' => $tag->id, 'metadata' => array_merge($tagActivity->metadata ?? [], ['restored' => true, 'restored_by' => auth()->id()])]);
                        }

                        ModerationLog::log('tags', 'restored', $tag, ['tag_name' => $name, 'maps_restored' => $reattached, 'maplists_restored' => $maplistReattached]);

                        $msg = "Tag \"{$name}\" restored with {$reattached} maps";
                        if ($maplistReattached > 0) $msg .= " and {$maplistReattached} maplists";
                        if (empty($mapIds) && empty($maplistIds)) $msg .= " (no map/maplist data in log - old format)";

                        Notification::make()->title($msg)->success()->send();
                    }),
                Tables\Actions\Action::make('revert_merge')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->label('Revert Merge')
                    ->visible(fn (ModerationLog $record) => $record->area === 'tags' && $record->action === 'merged' && !($record->metadata['reverted'] ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(function (ModerationLog $record) {
                        $meta = $record->metadata ?? [];
                        return "Revert merge of \"{$meta['source_tag']}\" into \"{$meta['target_tag']}\"?";
                    })
                    ->modalDescription(function (ModerationLog $record) {
                        $meta = $record->metadata ?? [];
                        if (!isset($meta['source_map_ids'])) {
                            return "Cannot revert - old log format without map data. The source tag would be recreated empty.";
                        }
                        $mapCount = count($meta['source_map_ids'] ?? []);
                        $maplistCount = count($meta['source_maplist_ids'] ?? []);
                        $existing = Tag::where('name', $meta['source_tag_normalized'] ?? strtolower($meta['source_tag'] ?? ''))->first();
                        if ($existing) {
                            return "Tag \"{$meta['source_tag']}\" already exists (ID: {$existing->id}). Cannot revert.";
                        }
                        return "This will recreate tag \"{$meta['source_tag']}\" and move {$mapCount} maps back from \"{$meta['target_tag']}\".";
                    })
                    ->action(function (ModerationLog $record): void {
                        $meta = $record->metadata ?? [];
                        $sourceName = $meta['source_tag'] ?? null;
                        $sourceNormalized = $meta['source_tag_normalized'] ?? strtolower($sourceName ?? '');
                        $targetTagId = $meta['target_tag_id'] ?? null;

                        if (!$sourceName) {
                            Notification::make()->title('Missing source tag data in log metadata')->danger()->send();
                            return;
                        }

                        $existing = Tag::where('name', $sourceNormalized)->first();
                        if ($existing) {
                            Notification::make()->title("Tag \"{$sourceName}\" already exists (ID: {$existing->id})")->danger()->send();
                            return;
                        }

                        // Recreate source tag
                        $sourceTag = Tag::create([
                            'name' => $sourceNormalized,
                            'display_name' => $sourceName,
                            'category' => $meta['source_tag_category'] ?? null,
                            'note' => $meta['source_tag_note'] ?? null,
                            'blocked_keywords' => $meta['source_tag_blocked_keywords'] ?? null,
                            'youtube_url' => $meta['source_tag_youtube_url'] ?? null,
                            'parent_tag_id' => $meta['source_tag_parent_tag_id'] ?? null,
                            'usage_count' => 0,
                        ]);

                        $targetTag = $targetTagId ? Tag::find($targetTagId) : null;
                        $movedMaps = 0;
                        $movedMaplists = 0;

                        $sourceMapIds = $meta['source_map_ids'] ?? [];
                        $targetOriginalMapIds = $meta['target_map_ids'] ?? [];
                        // Maps that were ONLY on source (not on target before merge) - these should be removed from target
                        $mapsToRemoveFromTarget = array_diff($sourceMapIds, $targetOriginalMapIds);

                        foreach ($sourceMapIds as $mapId) {
                            if (!\App\Models\Map::where('id', $mapId)->exists()) continue;

                            // Remove from target only if it wasn't originally on target
                            if ($targetTag && in_array($mapId, $mapsToRemoveFromTarget)) {
                                DB::table('map_tag')
                                    ->where('tag_id', $targetTag->id)
                                    ->where('map_id', $mapId)
                                    ->delete();
                            }

                            // Add to source tag
                            DB::table('map_tag')->insertOrIgnore([
                                'map_id' => $mapId,
                                'tag_id' => $sourceTag->id,
                                'user_id' => auth()->id(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $movedMaps++;
                        }

                        $sourceMaplistIds = $meta['source_maplist_ids'] ?? [];
                        $targetOriginalMaplistIds = $meta['target_maplist_ids'] ?? [];
                        $maplistsToRemoveFromTarget = array_diff($sourceMaplistIds, $targetOriginalMaplistIds);

                        foreach ($sourceMaplistIds as $maplistId) {
                            if (!\App\Models\Maplist::where('id', $maplistId)->exists()) continue;

                            if ($targetTag && in_array($maplistId, $maplistsToRemoveFromTarget)) {
                                DB::table('maplist_tag')
                                    ->where('tag_id', $targetTag->id)
                                    ->where('maplist_id', $maplistId)
                                    ->delete();
                            }

                            DB::table('maplist_tag')->insertOrIgnore([
                                'maplist_id' => $maplistId,
                                'tag_id' => $sourceTag->id,
                                'user_id' => auth()->id(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $movedMaplists++;
                        }

                        // Update usage counts
                        $sourceTag->update([
                            'usage_count' => DB::table('map_tag')->where('tag_id', $sourceTag->id)->count()
                                + DB::table('maplist_tag')->where('tag_id', $sourceTag->id)->count(),
                        ]);
                        if ($targetTag) {
                            $targetTag->update([
                                'usage_count' => DB::table('map_tag')->where('tag_id', $targetTag->id)->count()
                                    + DB::table('maplist_tag')->where('tag_id', $targetTag->id)->count(),
                            ]);
                        }

                        $record->update(['metadata' => array_merge($meta, ['reverted' => true, 'reverted_by' => auth()->id()])]);

                        ModerationLog::log('tags', 'reverted', $sourceTag, [
                            'tag_name' => $sourceName,
                            'original_action' => 'merged',
                            'result' => "unmerged from \"{$meta['target_tag']}\", {$movedMaps} maps moved back",
                        ]);

                        $msg = "Tag \"{$sourceName}\" recreated with {$movedMaps} maps";
                        if ($movedMaplists > 0) $msg .= " and {$movedMaplists} maplists";
                        if (empty($sourceMapIds) && empty($sourceMaplistIds)) $msg .= " (no map data in log - old format)";

                        Notification::make()->title($msg)->success()->send();
                    }),
                Tables\Actions\Action::make('revert')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->label('Revert')
                    ->visible(fn (ModerationLog $record) => in_array($record->action, ['approved', 'rejected', 'dismissed', 'claim_removed']) && !($record->metadata['reverted'] ?? false))
                    ->requiresConfirmation()
                    ->modalHeading(function (ModerationLog $record) {
                        if ($record->action === 'claim_removed') {
                            $meta = $record->metadata ?? [];
                            $claimName = $meta['claim_name'] ?? '?';
                            $claimType = $meta['claim_type'] ?? 'map';
                            $existingClaim = MapperClaim::where('name', $claimName)->where('type', $claimType)->with('user')->first();
                            if ($existingClaim) {
                                return "Claim \"{$claimName}\" is now owned by {$existingClaim->user?->plain_name}";
                            }
                            return "Restore claim \"{$claimName}\"?";
                        }
                        return 'Revert this action?';
                    })
                    ->modalDescription(function (ModerationLog $record) {
                        if ($record->action === 'claim_removed') {
                            $meta = $record->metadata ?? [];
                            $claimName = $meta['claim_name'] ?? '?';
                            $claimType = $meta['claim_type'] ?? 'map';
                            $originalUser = $meta['claim_user'] ?? '?';
                            $existingClaim = MapperClaim::where('name', $claimName)->where('type', $claimType)->with('user')->first();
                            if ($existingClaim) {
                                return "Someone else has claimed \"{$claimName}\" ({$claimType}) since it was removed. "
                                    . "Current owner: {$existingClaim->user?->plain_name}. "
                                    . "This will remove their claim and restore it to the original owner ({$originalUser}). "
                                    . "The report will be set back to pending.";
                            }
                            return "This will recreate the claim \"{$claimName}\" ({$claimType}) for {$originalUser} and set the report back to pending.";
                        }
                        return 'This will attempt to undo this moderation action.';
                    })
                    ->action(function (ModerationLog $record): void {
                        if ($record->action === 'claim_removed') {
                            $meta = $record->metadata ?? [];
                            $claimName = $meta['claim_name'] ?? null;
                            $claimUserId = $meta['claim_user_id'] ?? null;
                            $claimType = $meta['claim_type'] ?? 'map';

                            if (!$claimName || !$claimUserId) {
                                Notification::make()->title('Missing claim data in log metadata (old log format)')->danger()->send();
                                return;
                            }

                            // Remove any existing claim on the same name/type by another user
                            $existingClaim = MapperClaim::where('name', $claimName)->where('type', $claimType)->first();
                            if ($existingClaim && $existingClaim->user_id != $claimUserId) {
                                $existingClaim->delete();
                            }

                            // Recreate the original claim if it doesn't exist
                            $restoredClaim = MapperClaim::where('name', $claimName)->where('type', $claimType)->where('user_id', $claimUserId)->first();
                            if (!$restoredClaim) {
                                $restoredClaim = MapperClaim::create([
                                    'user_id' => $claimUserId,
                                    'name' => $claimName,
                                    'type' => $claimType,
                                ]);
                            }

                            // Re-link the report to the restored claim and set back to pending (if report still exists)
                            $report = $record->subject;
                            if ($report) {
                                $report->update([
                                    'mapper_claim_id' => $restoredClaim->id,
                                    'status' => 'pending',
                                    'resolved_at' => null,
                                    'resolved_by' => null,
                                ]);
                            }

                            $record->update(['metadata' => array_merge($meta, ['reverted' => true, 'reverted_by' => auth()->id()])]);

                            ModerationLog::log('mapper_claims', 'reverted', $report ?? $restoredClaim, [
                                'original_action' => 'claim_removed',
                                'original_log_id' => $record->id,
                                'claim_name' => $claimName,
                                'restored_to_user' => $meta['claim_user'] ?? '?',
                            ]);

                            $msg = "Claim \"{$claimName}\" restored to {$meta['claim_user']}";
                            if ($report) $msg .= ', report set to pending';
                            Notification::make()->title($msg)->success()->send();
                            return;
                        }

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
                        'reverted' => 'Reverted',
                        'tag_banned' => 'Tag Banned',
                        'tag_unbanned' => 'Tag Unbanned',
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
