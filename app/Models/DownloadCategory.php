<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadCategory extends Model
{
    use HasFactory;

    /**
     * Categories fed by a sync command instead of by people.
     */
    public const SOURCE_DEFRAG_MOD = 'defrag_mod';
    public const SOURCE_SERVER_BUNDLE = 'server_bundle';
    public const SOURCE_FAMILY_FRIENDLY = 'family_friendly';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'position',
        'is_locked',
        'auto_source',
        'created_by',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function downloads()
    {
        return $this->hasMany(Download::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * This category's id plus every descendant's, so a listing for a parent
     * also shows what sits in its subcategories. The tree is capped at three
     * levels, so a recursive walk stays cheap.
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
