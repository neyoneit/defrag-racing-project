<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DownloadFile extends Model
{
    use HasFactory;

    public const KIND_FILE = 'file';
    public const KIND_SCREENSHOT = 'screenshot';

    protected $fillable = [
        'download_id',
        'kind',
        'disk',
        'path',
        'original_name',
        'size',
        'extension',
        'position',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function download()
    {
        return $this->belongsTo(Download::class);
    }

    /**
     * A viewable link for this file, used by the screenshot gallery.
     *
     * On S3 that is a time-limited signed URL, because the community bucket is
     * private and a plain public URL would 401. A local disk can sign nothing
     * and exposes no public URL, so dev falls back to the app's own file
     * route, which streams the bytes.
     */
    public function viewUrl(int $minutes = 60): string
    {
        if (config("filesystems.disks.{$this->disk}.driver") === 's3') {
            return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
        }

        return route('downloads.file', $this);
    }
}
