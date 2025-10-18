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
        'base_model',
        'base_model_file_path',
        'model_type',
        'description',
        'category',
        'author',
        'author_email',
        'file_path',
        'zip_path',
        'thumbnail',
        'thumbnail_path',
        'head_icon',
        'downloads',
        'poly_count',
        'vert_count',
        'has_sounds',
        'has_ctf_skins',
        'available_skins',
        'approval_status',
        'hidden',
        'main_file',
    ];

    protected $casts = [
        'has_sounds' => 'boolean',
        'has_ctf_skins' => 'boolean',
        'hidden' => 'boolean',
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
        return $query->where('approval_status', 'approved');
    }

    /**
     * Scope for pending models only
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /**
     * Scope for rejected models only
     */
    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    /**
     * Scope for filtering by approval status
     */
    public function scopeApprovalStatus($query, $status)
    {
        if (in_array($status, ['pending', 'approved', 'rejected'])) {
            return $query->where('approval_status', $status);
        }
        return $query;
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
