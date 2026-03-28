<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'text',
        'type'
    ];

    protected static function booted(): void {
        static::created(function (Announcement $announcement) {
            Cache::forget('home:announcements');
            User::all()->each->systemNotifyAnnouncement('announcement', 'Announcement', $announcement->title, '', '/announcements');
        });

        static::updated(function (Announcement $announcement) {
            if ($announcement->isDirty('title')) {
                Notification::where('type', 'announcement')
                    ->where('headline', $announcement->getOriginal('title'))
                    ->update(['headline' => $announcement->title]);
            }
        });

        static::saved(function () {
            Cache::forget('home:announcements');
        });

        static::deleted(function () {
            Cache::forget('home:announcements');
        });
    }
}
