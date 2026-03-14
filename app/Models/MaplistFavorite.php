<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaplistFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'maplist_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function maplist()
    {
        return $this->belongsTo(Maplist::class);
    }
}
