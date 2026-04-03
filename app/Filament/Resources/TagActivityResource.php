<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagActivityResource\Pages;
use App\Models\TagActivity;
use App\Models\Tag;
use App\Models\Map;
use App\Models\Maplist;
use App\Models\ModerationLog;
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

    protected static ?int $navigationSort = 8;

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
                        $wasBanned = $user->tag_banned;
                        $user->tag_banned = !$user->tag_banned;
                        $user->save();

                        ModerationLog::log('tags', $user->tag_banned ? 'tag_banned' : 'tag_unbanned', $user, [
                            'tag_name' => $record->tag?->display_name ?? '[unknown]',
                            'banned_user' => $user->plain_name,
                        ]);

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

            $targetName = null;
            if ($record->taggable_type === 'App\\Models\\Map') {
                $targetName = Map::find($record->taggable_id)?->name;
            } elseif ($record->taggable_type === 'App\\Models\\Maplist') {
                $targetName = Maplist::find($record->taggable_id)?->name;
            }
            ModerationLog::log('tags', 'reverted', $tag, [
                'tag_name' => $tag->display_name,
                'original_action' => 'added',
                'result' => 'tag removed from ' . ($targetName ?? $record->taggable_type . '#' . $record->taggable_id),
                'original_user' => $record->user?->plain_name,
            ]);

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

            $targetName = null;
            if ($record->taggable_type === 'App\\Models\\Map') {
                $targetName = Map::find($record->taggable_id)?->name;
            } elseif ($record->taggable_type === 'App\\Models\\Maplist') {
                $targetName = Maplist::find($record->taggable_id)?->name;
            }
            ModerationLog::log('tags', 'reverted', $tag, [
                'tag_name' => $tag->display_name,
                'original_action' => 'removed',
                'result' => 'tag re-added to ' . ($targetName ?? $record->taggable_type . '#' . $record->taggable_id),
                'original_user' => $record->user?->plain_name,
            ]);

            return true;
        }

        return 'Cannot revert this action type.';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('action', ['added', 'removed']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTagActivities::route('/'),
        ];
    }
}
