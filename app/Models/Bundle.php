<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'url',
        'file',
        'category_id',
        'position',
    ];

    public function category()
    {
        return $this->belongsTo(BundleCategory::class, 'category_id');
    }
}
