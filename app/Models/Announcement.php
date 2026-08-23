<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Announcement extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'title',
        'text',
        'type'
    ];

    /**
     * An announcement goes on the home page and nowhere else. `type` is left
     * over from an older arrangement that had somewhere else to send one, and
     * every read since is `where('type', 'home')` - so it is filled in here
     * rather than asked for. It stayed a required free-text box in the admin
     * for a long time, where anything but the one right word made the
     * announcement disappear from the whole site with nothing said.
     */
    protected $attributes = [
        'type' => 'home',
    ];

    public array $translatable = ['title', 'text'];

    protected static function booted(): void {
        static::created(function (Announcement $announcement) {
            Cache::forget('home:announcements');
            User::all()->each->systemNotifyAnnouncement('announcement', 'Announcement', $announcement->title, '', '/announcements', $announcement->id);
        });

        // The rows are found by id rather than by the old title they still
        // carry. Two announcements that were once named the same thing used to
        // rewrite each other's notifications.
        static::updated(function (Announcement $announcement) {
            if ($announcement->isDirty('title')) {
                Notification::where('type', 'announcement')
                    ->where('announcement_id', $announcement->id)
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
