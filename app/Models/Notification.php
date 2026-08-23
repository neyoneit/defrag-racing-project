<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'before',
        'headline',
        'after',
        'subheadline',
        'image',
        'url',
        'read',
        'type',
        'user_id',
        'announcement_id',
    ];

    /**
     * Every page that shows a notification wants the translated headline, and
     * none of them should have to know that announcements are the one type
     * where the stored text is a copy. Appending it means a consumer reads one
     * field and is right for all of them.
     */
    protected $appends = ['headline_localized'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Set on announcement notifications only; null on every other type. */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * The headline in the reader's language.
     *
     * The row stores the English title as it stood when the announcement was
     * created. The announcement itself is translated, so for those rows the
     * title is read back through it and falls back to English one field at a
     * time, exactly as everywhere else on the site. Every other type has no
     * announcement and no translations, and gets its stored text unchanged.
     *
     * Callers that list notifications should eager load `announcement.
     * translations`; without it this is correct but costs a query per
     * announcement.
     */
    public function getHeadlineLocalizedAttribute(): ?string
    {
        if (! $this->announcement_id || app()->getLocale() === 'en') {
            return $this->headline;
        }

        return $this->announcement?->tr('title') ?? $this->headline;
    }
}
