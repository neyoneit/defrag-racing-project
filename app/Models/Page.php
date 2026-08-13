<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'visible',
        'footer_link'
    ];

    /** The terms and the privacy policy are read by the whole community. */
    public array $translatable = ['title', 'content'];
}
