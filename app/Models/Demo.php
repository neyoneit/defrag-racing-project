<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Demo extends Model {
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'round_id',
        'user_id',
        'file',
        'filename',
        'time',
        'rank',
        'physics',
        'points',
        'approved',
        'rejected',
        'counted',
        'reason',
    ];

    protected $width = ['user'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function round() {
        return $this->belongsTo(Round::class);
    }

    public function toSearchableArray() {
        return [
            'id' => (string) $this->id,
            'filename' => $this->filename,
            'created_at' => $this->created_at->timestamp,
        ];
    }
}
