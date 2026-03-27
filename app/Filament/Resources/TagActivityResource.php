<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagActivityResource\Pages;
use App\Models\TagActivity;
use App\Models\Tag;
use App\Models\Map;
use App\Models\Maplist;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TagActivityResource extends Resource
{
    protected static ?string $model = TagActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Tag Logs';

    protected static ?string $modelLabel = 'Tag Activity';

    protected static ?string $pluralModelLabel = 'Tag Activities';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('tags') ?? false;
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
                    ->url(fn ($record) => $record->user ? route('profile.index', $record->user) : null)
                    ->openUrlInNewTab()
                    ->icon(fn ($record) => $record->user?->tag_banned ? 'heroicon-s-no-symbol' : null)
                    ->iconColor('danger')
                    ->tooltip(fn ($record) => $record->user?->tag_banned ? 'Tag banned' : null),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'added' => 'success',
                        'removed' => 'danger',
                        'merged' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('tag.display_name')
                    ->label('Tag')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->tag?->display_name ?? ($record->metadata['tag_name'] ?? '[deleted]'))
                    ->description(fn ($record) => match ($record->action) {
                        'merged' => 'into: ' . ($record->metadata['target_tag'] ?? '?'),
                        'deleted' => 'TAG DELETED',
                        default => null,
                    })
                    ->color(fn ($record) => $record->action === 'deleted' ? 'danger' : null),
                Tables\Columns\TextColumn::make('target_name')
                    ->label('On')
                    ->getStateUsing(function ($record) {
                        if ($record->action === 'merged') {
                            return $record->metadata['source_tag'] ?? '?';
                        }
                        if ($record->taggable_type === 'App\\Models\\Map') {
                            $map = \App\Models\Map::find($record->taggable_id);
                            return $map ? $map->name : "Map #{$record->taggable_id}";
                        }
                        if ($record->taggable_type === 'App\\Models\\Maplist') {
                            $maplist = \App\Models\Maplist::find($record->taggable_id);
                            return $maplist ? $maplist->name : "Maplist #{$record->taggable_id}";
                        }
                        return '-';
                    })
                    ->url(function ($record) {
                        if ($record->taggable_type === 'App\\Models\\Map') {
                            $map = \App\Models\Map::find($record->taggable_id);
                            return $map ? route('maps.map', $map->name) : null;
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->whereHasMorph('taggable', [\App\Models\Map::class], function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%");
                            });
                        });
                    }),
                Tables\Columns\TextColumn::make('metadata')
                    ->label('Details')
                    ->getStateUsing(function ($record) {
                        if (!$record->metadata) return null;
                        if ($record->action === 'merged') {
                            return "→ {$record->metadata['target_tag']}";
                        }
                        if ($record->action === 'deleted') {
                            $parts = ['"' . ($record->metadata['tag_name'] ?? '?') . '"'];
                            if ($record->metadata['usage_count'] ?? 0) $parts[] = $record->metadata['usage_count'] . ' uses';
                            $mapCount = count($record->metadata['map_ids'] ?? []);
                            if ($mapCount) $parts[] = $mapCount . ' maps';
                            if ($record->metadata['restored'] ?? false) $parts[] = 'RESTORED';
                            return implode(', ', $parts);
                        }
                        $parts = [];
                        if ($record->metadata['reverted'] ?? false) $parts[] = 'REVERTED';
                        if ($record->metadata['reverted_action_id'] ?? false) $parts[] = 'revert of #' . $record->metadata['reverted_action_id'];
                        if ($record->metadata['auto_parent'] ?? false) $parts[] = 'auto (parent)';
                        if ($record->metadata['auto_child'] ?? false) $parts[] = 'auto (child removed)';
                        return implode(', ', $parts) ?: null;
                    })
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'added' => 'Added',
                        'removed' => 'Removed',
                        'merged' => 'Merged',
                        'deleted' => 'Deleted',
                    ]),
                Tables\Filters\SelectFilter::make('tag_id')
                    ->label('Tag')
                    ->options(fn () => Tag::orderBy('display_name')->pluck('display_name', 'id'))
                    ->searchable(),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->options(fn () => User::whereIn('id', TagActivity::distinct()->pluck('user_id'))
                        ->orderBy('plain_name')
                        ->pluck('plain_name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_ban')
                    ->icon(fn (TagActivity $record) => $record->user?->tag_banned ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
                    ->color(fn (TagActivity $record) => $record->user?->tag_banned ? 'success' : 'danger')
                    ->label(fn (TagActivity $record) => $record->user?->tag_banned ? 'Unban Tags' : 'Tag Ban')
                    ->requiresConfirmation()
                    ->modalHeading(fn (TagActivity $record) => $record->user?->tag_banned
                        ? "Unban \"{$record->user->plain_name}\" from tags?"
                        : "Ban \"{$record->user?->plain_name}\" from tags?")
                    ->modalDescription(fn (TagActivity $record) => $record->user?->tag_banned
                        ? 'This user will be able to add and remove tags again.'
                        : 'This user will no longer be able to add or remove tags on maps.')
                    ->visible(fn (TagActivity $record) => !$record->user?->admin && !$record->user?->is_moderator)
                    ->action(function (TagActivity $record): void {
                        $user = $record->user;
                        if (!$user) return;
                        if ($user->admin || $user->is_moderator) {
                            Notification::make()->title('Cannot ban admins or moderators.')->danger()->send();
                            return;
                        }
                        $user->tag_banned = !$user->tag_banned;
                        $user->save();
                        Notification::make()
                            ->title($user->tag_banned
                                ? "\"{$user->plain_name}\" has been tag banned."
                                : "\"{$user->plain_name}\" has been unbanned.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('revert')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->label('Revert')
                    ->visible(fn (TagActivity $record) => in_array($record->action, ['added', 'removed']) && !($record->metadata['reverted'] ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Revert this action?')
                    ->modalDescription(fn (TagActivity $record) => match ($record->action) {
                        'added' => "This will REMOVE tag \"{$record->tag?->display_name}\" from the map/maplist.",
                        'removed' => "This will RE-ADD tag \"{$record->tag?->display_name}\" to the map/maplist.",
                        default => '',
                    })
                    ->action(function (TagActivity $record): void {
                        $result = static::revertAction($record);
                        if ($result === true) {
                            Notification::make()->title('Action reverted')->success()->send();
                        } else {
                            Notification::make()->title('Could not revert')->body($result)->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('restore')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->label('Restore')
                    ->visible(fn (TagActivity $record) => $record->action === 'deleted' && !($record->metadata['restored'] ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Restore deleted tag?')
                    ->modalDescription(function (TagActivity $record) {
                        $name = $record->metadata['tag_name'] ?? '?';
                        $mapCount = count($record->metadata['map_ids'] ?? []);
                        $maplistCount = count($record->metadata['maplist_ids'] ?? []);
                        $parts = ["This will recreate tag \"{$name}\""];
                        if ($mapCount) $parts[] = "and re-attach it to {$mapCount} maps";
                        if ($maplistCount) $parts[] = "and {$maplistCount} maplists";
                        return implode(' ', $parts) . '.';
                    })
                    ->action(function (TagActivity $record): void {
                        $meta = $record->metadata ?? [];
                        $name = $meta['tag_name'] ?? null;
                        $normalized = $meta['tag_name_normalized'] ?? strtolower($name ?? '');

                        if (!$name) {
                            Notification::make()->title('Cannot restore - no tag name in metadata')->danger()->send();
                            return;
                        }

                        // Check if tag already exists (someone recreated it)
                        $existing = Tag::where('name', $normalized)->first();
                        if ($existing) {
                            Notification::make()->title("Tag \"{$name}\" already exists (ID: {$existing->id})")->danger()->send();
                            return;
                        }

                        // Recreate tag
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

                        // Re-attach to maps
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

                        // Re-attach to maplists
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

                        // Update usage count
                        $tag->update([
                            'usage_count' => DB::table('map_tag')->where('tag_id', $tag->id)->count()
                                + DB::table('maplist_tag')->where('tag_id', $tag->id)->count(),
                        ]);

                        // Mark as restored
                        $record->update([
                            'tag_id' => $tag->id,
                            'metadata' => array_merge($meta, ['restored' => true, 'restored_by' => auth()->id()]),
                        ]);

                        TagActivity::log('added', auth()->id(), $tag->id, 'restore', 0, [
                            'restored_from_activity_id' => $record->id,
                        ]);

                        Notification::make()
                            ->title("Tag \"{$name}\" restored with {$reattached} maps")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('revert_selected')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->label('Revert selected')
                        ->requiresConfirmation()
                        ->modalHeading('Revert selected actions?')
                        ->modalDescription('This will undo all selected add/remove actions. Merge actions cannot be reverted.')
                        ->action(function (Collection $records): void {
                            $reverted = 0;
                            $skipped = 0;
                            foreach ($records->sortByDesc('created_at') as $record) {
                                if (!in_array($record->action, ['added', 'removed'])) {
                                    $skipped++;
                                    continue;
                                }
                                $result = static::revertAction($record);
                                if ($result === true) {
                                    $reverted++;
                                } else {
                                    $skipped++;
                                }
                            }
                            Notification::make()
                                ->title("Reverted {$reverted} actions" . ($skipped ? ", skipped {$skipped}" : ''))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    protected static function revertAction(TagActivity $record): true|string
    {
        $tag = Tag::find($record->tag_id);
        if (!$tag) return 'Tag no longer exists.';

        if ($record->action === 'added') {
            // Revert add = remove the tag
            $table = match ($record->taggable_type) {
                'App\\Models\\Map' => 'map_tag',
                'App\\Models\\Maplist' => 'maplist_tag',
                default => null,
            };
            $fk = match ($record->taggable_type) {
                'App\\Models\\Map' => 'map_id',
                'App\\Models\\Maplist' => 'maplist_id',
                default => null,
            };
            if (!$table) return 'Unknown taggable type.';

            $existed = DB::table($table)
                ->where('tag_id', $tag->id)
                ->where($fk, $record->taggable_id)
                ->exists();

            if ($existed) {
                DB::table($table)
                    ->where('tag_id', $tag->id)
                    ->where($fk, $record->taggable_id)
                    ->delete();
                $tag->decrementUsage();
            }

            TagActivity::log('removed', auth()->id(), $tag->id, $record->taggable_type, $record->taggable_id, ['reverted_action_id' => $record->id]);
            $record->update(['metadata' => array_merge($record->metadata ?? [], ['reverted' => true, 'reverted_by' => auth()->id()])]);
            return true;
        }

        if ($record->action === 'removed') {
            // Revert remove = re-add the tag
            $table = match ($record->taggable_type) {
                'App\\Models\\Map' => 'map_tag',
                'App\\Models\\Maplist' => 'maplist_tag',
                default => null,
            };
            $fk = match ($record->taggable_type) {
                'App\\Models\\Map' => 'map_id',
                'App\\Models\\Maplist' => 'maplist_id',
                default => null,
            };
            if (!$table) return 'Unknown taggable type.';

            $alreadyExists = DB::table($table)
                ->where('tag_id', $tag->id)
                ->where($fk, $record->taggable_id)
                ->exists();

            if (!$alreadyExists) {
                DB::table($table)->insert([
                    'tag_id' => $tag->id,
                    $fk => $record->taggable_id,
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $tag->incrementUsage();
            }

            TagActivity::log('added', auth()->id(), $tag->id, $record->taggable_type, $record->taggable_id, ['reverted_action_id' => $record->id]);
            $record->update(['metadata' => array_merge($record->metadata ?? [], ['reverted' => true, 'reverted_by' => auth()->id()])]);
            return true;
        }

        return 'Cannot revert this action type.';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTagActivities::route('/'),
        ];
    }
}
