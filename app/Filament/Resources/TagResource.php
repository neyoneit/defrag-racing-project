<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Filament\Resources\TagResource\RelationManagers;
use App\Models\Tag;
use App\Models\TagActivity;
use App\Models\Map;
use App\Models\Maplist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('tags') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tag Hierarchy')
                    ->description('Tags can have a parent-child relationship. When a child tag (e.g. "PGB") is added to a map, the parent tag (e.g. "Ground Boost") is automatically added too. When a parent tag is removed, all its children are removed from that map.')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Placeholder::make('hierarchy_info')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="text-sm space-y-2">' .
                                '<p><strong>Example:</strong> "Ground Boost" is a parent tag. "PGB" (Plasma Ground Boost) and "RGB" (Rocket Ground Boost) are its children.</p>' .
                                '<ul class="list-disc pl-5 space-y-1">' .
                                '<li>Adding "PGB" to a map → "Ground Boost" is <strong>auto-added</strong></li>' .
                                '<li>Adding "Ground Boost" to a map → nothing extra happens</li>' .
                                '<li>Removing "PGB" from a map → only "PGB" is removed, "Ground Boost" stays</li>' .
                                '<li>Removing "Ground Boost" from a map → "PGB" and "RGB" are <strong>also removed</strong></li>' .
                                '<li>Setting a parent <strong>retroactively adds</strong> the parent tag to all maps that already have this child tag</li>' .
                                '</ul>' .
                                '</div>'
                            ))
                            ->hiddenLabel(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\TextInput::make('display_name')
                    ->required()
                    ->maxLength(255)
                    ->label('Display Name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        if ($state) {
                            $set('name', strtolower(trim($state)));
                        }
                    }),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Normalized Name')
                    ->unique(ignoreRecord: true)
                    ->helperText('Lowercase, used for deduplication'),
                Forms\Components\TextInput::make('category')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\TextInput::make('note')
                    ->maxLength(255)
                    ->nullable()
                    ->helperText('Short description shown as tooltip when hovering the tag on map detail'),
                Forms\Components\Select::make('parent_tag_id')
                    ->label('Parent Tag')
                    ->helperText('If set, adding this tag to a map will also auto-add the parent tag. Only tags without a parent can be selected (no multi-level nesting).')
                    ->relationship('parent', 'display_name')
                    ->options(fn (?Tag $record) => Tag::whereNull('parent_tag_id')
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                        ->orderBy('display_name')
                        ->pluck('display_name', 'id')
                    )
                    ->searchable()
                    ->nullable()
                    ->preload(),
                Forms\Components\Placeholder::make('children_info')
                    ->label('Child Tags')
                    ->content(function (?Tag $record) {
                        if (!$record) return 'Save tag first to see children.';
                        $children = $record->children()->pluck('display_name')->toArray();
                        if (empty($children)) return 'No child tags. Other tags can reference this tag as their parent.';
                        return implode(', ', $children);
                    })
                    ->visibleOn('edit'),
                Forms\Components\TextInput::make('usage_count')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(false)
                    ->label('Usage Count'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('usage_count', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->sortable()
                    ->label('Tag'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Normalized')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'weapons' => 'danger',
                        'items' => 'warning',
                        'functions' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('usage_count')
                    ->numeric()
                    ->sortable()
                    ->label('Uses'),
                Tables\Columns\TextColumn::make('maps_count')
                    ->counts('maps')
                    ->sortable()
                    ->label('Maps'),
                Tables\Columns\TextColumn::make('maplists_count')
                    ->counts('maplists')
                    ->sortable()
                    ->label('Maplists'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn () => Tag::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->pluck('category', 'category')
                        ->toArray()
                    ),
                Tables\Filters\Filter::make('unused')
                    ->query(fn (Builder $query): Builder => $query->where('usage_count', 0))
                    ->label('Unused tags only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('merge')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->label('Merge into...')
                    ->form([
                        Forms\Components\Select::make('target_tag_id')
                            ->label('Merge into this tag')
                            ->options(fn (Tag $record) => Tag::where('id', '!=', $record->id)
                                ->orderBy('display_name')
                                ->pluck('display_name', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Tag $record, array $data): void {
                        $targetTag = Tag::find($data['target_tag_id']);
                        if (!$targetTag) return;

                        // Move all map_tag relations to target (skip duplicates)
                        // MySQL can't UPDATE and SELECT from same table in subquery, so collect IDs first
                        $existingMapIds = DB::table('map_tag')->where('tag_id', $targetTag->id)->pluck('map_id')->all();
                        $query = DB::table('map_tag')->where('tag_id', $record->id);
                        if (!empty($existingMapIds)) {
                            $query->whereNotIn('map_id', $existingMapIds);
                        }
                        $query->update(['tag_id' => $targetTag->id]);

                        // Move all maplist_tag relations to target (skip duplicates)
                        $existingMaplistIds = DB::table('maplist_tag')->where('tag_id', $targetTag->id)->pluck('maplist_id')->all();
                        $query = DB::table('maplist_tag')->where('tag_id', $record->id);
                        if (!empty($existingMaplistIds)) {
                            $query->whereNotIn('maplist_id', $existingMaplistIds);
                        }
                        $query->update(['tag_id' => $targetTag->id]);

                        // Delete remaining duplicate pivots for source tag
                        DB::table('map_tag')->where('tag_id', $record->id)->delete();
                        DB::table('maplist_tag')->where('tag_id', $record->id)->delete();

                        // Recalculate usage count on target
                        $targetTag->update([
                            'usage_count' => DB::table('map_tag')->where('tag_id', $targetTag->id)->count()
                                + DB::table('maplist_tag')->where('tag_id', $targetTag->id)->count(),
                        ]);

                        // Log the merge
                        TagActivity::log('merged', auth()->id(), $record->id, 'merge', 0, [
                            'source_tag' => $record->display_name,
                            'target_tag_id' => $targetTag->id,
                            'target_tag' => $targetTag->display_name,
                        ]);

                        // Delete source tag
                        $record->delete();

                        Notification::make()
                            ->title("Merged into \"{$targetTag->display_name}\"")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Merge Tag')
                    ->modalDescription(fn (Tag $record) => "All maps and maplists using \"{$record->display_name}\" will be moved to the target tag. The source tag will be deleted."),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('merge_bulk')
                        ->icon('heroicon-o-arrows-pointing-in')
                        ->color('warning')
                        ->label('Merge selected into...')
                        ->form([
                            Forms\Components\Select::make('target_tag_id')
                                ->label('Merge all selected into this tag')
                                ->options(fn () => Tag::orderBy('display_name')->pluck('display_name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $targetTag = Tag::find($data['target_tag_id']);
                            if (!$targetTag) return;

                            foreach ($records as $record) {
                                if ($record->id === $targetTag->id) continue;

                                DB::table('map_tag')
                                    ->where('tag_id', $record->id)
                                    ->whereNotIn('map_id', function ($q) use ($targetTag) {
                                        $q->select('map_id')->from('map_tag')->where('tag_id', $targetTag->id);
                                    })
                                    ->update(['tag_id' => $targetTag->id]);

                                DB::table('maplist_tag')
                                    ->where('tag_id', $record->id)
                                    ->whereNotIn('maplist_id', function ($q) use ($targetTag) {
                                        $q->select('maplist_id')->from('maplist_tag')->where('tag_id', $targetTag->id);
                                    })
                                    ->update(['tag_id' => $targetTag->id]);

                                DB::table('map_tag')->where('tag_id', $record->id)->delete();
                                DB::table('maplist_tag')->where('tag_id', $record->id)->delete();

                                TagActivity::log('merged', auth()->id(), $record->id, 'merge', 0, [
                                    'source_tag' => $record->display_name,
                                    'target_tag_id' => $targetTag->id,
                                    'target_tag' => $targetTag->display_name,
                                ]);

                                $record->delete();
                            }

                            $targetTag->update([
                                'usage_count' => DB::table('map_tag')->where('tag_id', $targetTag->id)->count()
                                    + DB::table('maplist_tag')->where('tag_id', $targetTag->id)->count(),
                            ]);

                            Notification::make()
                                ->title('Tags merged successfully')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MapsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
