<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerModel extends Model
{
    use HasFactory;

    protected $table = 'models';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'author',
        'author_email',
        'file_path',
        'zip_path',
        'thumbnail',
        'downloads',
        'poly_count',
        'vert_count',
        'has_sounds',
        'has_ctf_skins',
        'available_skins',
        'approved',
    ];

    protected $casts = [
        'has_sounds' => 'boolean',
        'has_ctf_skins' => 'boolean',
        'approved' => 'boolean',
        'downloads' => 'integer',
        'poly_count' => 'integer',
        'vert_count' => 'integer',
        'available_skins' => 'array',
    ];

    /**
     * Get the user who uploaded the model
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for approved models only
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    /**
     * Scope for filtering by category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Increment download count
     */
    public function incrementDownloads()
    {
        $this->increment('downloads');
    }
}
