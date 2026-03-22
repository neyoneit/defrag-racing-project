<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use App\Models\Tag;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $tag = $this->record->fresh();

        // If this tag has a parent, retroactively add the parent tag to all maps
        // that already have this child tag but don't have the parent yet
        if ($tag->parent_tag_id) {
            $mapsWithChild = DB::table('map_tag')
                ->where('tag_id', $tag->id)
                ->pluck('map_id');

            $mapsAlreadyWithParent = DB::table('map_tag')
                ->where('tag_id', $tag->parent_tag_id)
                ->whereIn('map_id', $mapsWithChild)
                ->pluck('map_id');

            $mapsNeedingParent = $mapsWithChild->diff($mapsAlreadyWithParent);

            if ($mapsNeedingParent->isNotEmpty()) {
                $inserts = $mapsNeedingParent->map(fn ($mapId) => [
                    'map_id' => $mapId,
                    'tag_id' => $tag->parent_tag_id,
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                DB::table('map_tag')->insert($inserts);

                // Update parent usage count
                $parentTag = Tag::find($tag->parent_tag_id);
                if ($parentTag) {
                    $parentTag->update([
                        'usage_count' => DB::table('map_tag')->where('tag_id', $parentTag->id)->count()
                            + DB::table('maplist_tag')->where('tag_id', $parentTag->id)->count(),
                    ]);
                }

                Notification::make()
                    ->title("Parent tag added to {$mapsNeedingParent->count()} maps retroactively")
                    ->success()
                    ->send();
            }
        }
    }
}
