<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WikiPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'content',
        'parent_id',
        'sort_order',
        'is_locked',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WikiPage::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WikiPage::class, 'parent_id')->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WikiRevision::class)->orderByDesc('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function createRevision(int $userId, ?string $summary = null): WikiRevision
    {
        return $this->revisions()->create([
            'title' => $this->title,
            'content' => $this->content,
            'user_id' => $userId,
            'summary' => $summary,
        ]);
    }
}
