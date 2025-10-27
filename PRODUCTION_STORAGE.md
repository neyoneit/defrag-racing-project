# Production Storage Setup

## Architecture

**Production Setup:**
- **Processing VPS**: Runs Laravel app, processes demos, handles uploads
- **Storage VPS/S3**: Stores compressed demo files (.7z)

## Option 1: S3-Compatible Object Storage (RECOMMENDED)

### Why S3?
- ✅ Laravel has native S3 support
- ✅ Automatic replication and backups
- ✅ CDN integration for fast downloads
- ✅ Handles deletions/reuploads automatically
- ✅ Scalable and cost-effective
- ✅ No server maintenance needed

### Recommended Providers

| Provider | Cost | Pros |
|----------|------|------|
| **DigitalOcean Spaces** | $5/mo (250GB) | Easy, integrated with DO |
| **Backblaze B2** | $5/TB/mo | Cheapest, free egress to Cloudflare |
| **Wasabi** | $6.99/TB/mo | No egress fees, very fast |
| **AWS S3** | ~$23/TB/mo | Industry standard, expensive |

### Setup Steps

#### 1. Create S3-Compatible Bucket

**For DigitalOcean Spaces:**
```bash
# Create Space in DO dashboard
# Region: Choose closest to your users (e.g., nyc3, ams3, sgp1)
# Name: defrag-demos
# Enable CDN (optional, for faster downloads)
```

#### 2. Configure Laravel

Install S3 package:
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

#### 3. Update .env

```env
# Storage Configuration
FILESYSTEM_DISK=s3

# S3 Credentials
AWS_ACCESS_KEY_ID=your_spaces_key
AWS_SECRET_ACCESS_KEY=your_spaces_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=defrag-demos

# For DigitalOcean Spaces
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
AWS_URL=https://defrag-demos.nyc3.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false

# For Backblaze B2
# AWS_ENDPOINT=https://s3.us-west-004.backblazeb2.com
# AWS_URL=https://f004.backblazeb2.com/file/defrag-demos

# For Wasabi
# AWS_ENDPOINT=https://s3.us-east-1.wasabisys.com
# AWS_URL=https://defrag-demos.s3.us-east-1.wasabisys.com
```

#### 4. Update config/filesystems.php

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'visibility' => 'public', // For downloadable demos
],
```

#### 5. Test Connection

```bash
php artisan tinker

# Test S3 connection
Storage::disk('s3')->put('test.txt', 'Hello World');
Storage::disk('s3')->exists('test.txt'); // Should return true
Storage::disk('s3')->delete('test.txt');
```

#### 6. Update Code (if needed)

The code already uses `Storage::disk()` facade, so it should work automatically. Verify:

```php
// DemoProcessorService.php already uses Storage facade
Storage::put($compressedPath, file_get_contents($tempCompressedPath));
Storage::size($compressedPath);

// DemosController.php already uses Storage facade
Storage::download($demo->file_path, $filename);
```

### Migration from Local to S3

If you have existing demos on local storage:

```bash
# Sync local demos to S3
php artisan tinker

// Get all demos
$demos = \App\Models\Demo::whereNotNull('file')->get();

foreach ($demos as $demo) {
    $localPath = storage_path('app/' . $demo->file);
    if (file_exists($localPath)) {
        // Copy to S3
        $contents = file_get_contents($localPath);
        Storage::disk('s3')->put($demo->file, $contents);
        echo "Migrated demo {$demo->id}\n";
    }
}
```

Or use AWS CLI for bulk transfer:
```bash
# Install AWS CLI
sudo apt install awscli

# Configure
aws configure

# Sync entire directory
aws s3 sync storage/app/demos/ s3://defrag-demos/demos/ --endpoint-url=https://nyc3.digitaloceanspaces.com
```

### Download Performance Optimization

**Option A: Direct CDN URLs (Recommended)**

Configure S3 bucket/Space to allow public reads, then generate direct URLs:

```php
// In DemosController.php
public function download(UploadedDemo $demo)
{
    // Generate temporary signed URL (expires in 1 hour)
    $url = Storage::disk('s3')->temporaryUrl(
        $demo->file,
        now()->addHour()
    );

    return redirect($url);
}
```

**Option B: Proxy through Laravel**

Keep current implementation - Laravel streams from S3:
```php
return Storage::download($demo->file_path, $filename);
```

Pros: Better access control, track downloads
Cons: Uses your server's bandwidth

---

## Option 2: SFTP to Storage VPS

### Setup Steps

#### 1. Install SFTP Package

```bash
composer require league/flysystem-sftp-v3 "^3.0"
```

#### 2. Configure .env

```env
SFTP_HOST=storage.example.com
SFTP_PORT=22
SFTP_USERNAME=defrag-storage
SFTP_PASSWORD=your_secure_password
SFTP_ROOT=/home/defrag-storage/demos
SFTP_TIMEOUT=30
```

#### 3. Update config/filesystems.php

```php
'sftp' => [
    'driver' => 'sftp',
    'host' => env('SFTP_HOST'),
    'port' => env('SFTP_PORT', 22),
    'username' => env('SFTP_USERNAME'),
    'password' => env('SFTP_PASSWORD'),
    'root' => env('SFTP_ROOT', ''),
    'timeout' => env('SFTP_TIMEOUT', 30),
    'directoryPerm' => 0755,
    'visibility' => 'public',
],
```

#### 4. Set Filesystem Disk

```env
FILESYSTEM_DISK=sftp
```

**Pros:**
- Full control over storage VPS
- No monthly service fees
- Works with any Linux VPS

**Cons:**
- Slower than S3
- Single point of failure
- Manual backups needed
- SSH key management

---

## Option 3: NFS Mount (NOT RECOMMENDED)

**Why not NFS:**
- Network latency affects performance
- Complex setup with permissions
- Requires VPN or firewall rules
- Not suitable for cloud/distributed architecture
- Poor handling of concurrent access

If you still want NFS, use Option 1 (S3) instead.

---

## Handling Deletions and Reuploads

All storage drivers (S3, SFTP, local) handle this automatically via Laravel's Storage facade:

```php
// Delete demo file
Storage::delete($demo->file);  // Works on any driver

// Reupload (overwrite)
Storage::put($path, $newContent);  // Overwrites existing file

// Unassign and delete
if ($demo->file) {
    Storage::delete($demo->file);
    $demo->file = null;
    $demo->save();
}
```

The code in `DemosController.php` already handles this correctly:

```php
// Delete demo
public function destroy(UploadedDemo $demo)
{
    if ($demo->file) {
        Storage::delete($demo->file);  // Auto-deletes from S3/SFTP/local
    }
    $demo->delete();
}

// Unassign from record
public function unassign(UploadedDemo $demo)
{
    $demo->record_id = null;
    $demo->save();
    // File remains in storage for potential reassignment
}
```

---

## Recommended Configuration for Production

### For Small to Medium Traffic:
- **Storage**: DigitalOcean Spaces ($5/mo)
- **Processing VPS**: DigitalOcean Droplet ($12-24/mo)
- **CDN**: Use Spaces CDN (included)

### For High Traffic:
- **Storage**: Backblaze B2 ($5/TB)
- **CDN**: Cloudflare (free egress from B2)
- **Processing VPS**: Higher-tier droplet with Redis

### Cost Comparison (1TB storage):
- **DigitalOcean Spaces**: ~$50/mo (4x $5 tiers + overages)
- **Backblaze B2**: $5/mo storage + $10/mo egress = $15/mo
- **Wasabi**: $6.99/mo (no egress fees)
- **Self-hosted SFTP VPS**: $6/mo VPS + backups

---

## Testing Checklist

Before going to production:

- [ ] Test upload → processing → storage to S3
- [ ] Test download from S3
- [ ] Test deletion from S3
- [ ] Test reupload/overwrite
- [ ] Test unassign (file should stay)
- [ ] Verify permissions (files should be private or signed URLs)
- [ ] Test with .7z compressed files
- [ ] Load test with 100+ demos
- [ ] Verify CDN caching (if using CDN)
- [ ] Test fallback to direct download if S3 fails

---

## Monitoring

Add logging to track storage operations:

```php
// In DemoProcessorService.php
Log::info('Demo stored to S3', [
    'demo_id' => $demo->id,
    'file_path' => $compressedPath,
    'file_size' => $compressedSize,
    'storage_driver' => config('filesystems.default'),
]);
```

Monitor S3 metrics:
- Storage usage
- Bandwidth/egress
- Request counts
- Error rates

---

## Demo Upload and Storage Flow

### How Demo Processing Works

**Upload Flow:**
1. User uploads demo file(s) via `/demos` page (supports .dm_68 files and .zip/.rar/.7z archives)
2. Raw demo files stored LOCALLY in `storage/app/demos/temp/{demo_id}/`
3. Demo record created in database with `status='queued'`
4. Processing job dispatched to queue

**Processing Flow:**
1. Python script (`BatchDemoRenamer.py`) extracts metadata:
   - Map name
   - Physics (VQ3/CPM)
   - Gametype (df/mdf/fs/mfs/fc/mfc - offline vs online)
   - Time (milliseconds)
   - Player name
2. Demo compressed to .7z format
3. **Compressed .7z uploaded to Backblaze** at simple path: `demos/{filename}.7z`
4. Metadata saved to database
5. Local temp files cleaned up
6. Demo status updated to `processed`
7. **Auto-assignment logic:**
   - **Online demos** (mdf, mfs, mfc) → Can be assigned to records from q3df.org
   - **Offline demos** (df, fs, fc) → NOT assigned to records (will have separate offline leaderboards)

**Success:** Demo exists in Backblaze, local temp deleted
**Failure (after 3 retries):** Raw demo moved to `storage/app/demos/failed/{demo_id}/` for admin review

### Storage Locations

| Status | Location | Example |
|--------|----------|---------|
| **Queued** | Local temp | `storage/app/demos/temp/123/mapname.dm_68` |
| **Processing** | Local temp | `storage/app/demos/temp/123/mapname.dm_68` |
| **Processed** | Backblaze B2 | `demos/mapname[df.vq3]01.23.456(player).7z` |
| **Failed** | Local failed | `storage/app/demos/failed/123/mapname.dm_68` |

### Why This Design?

- ✅ **Cost Efficient**: Only upload processed/compressed demos to Backblaze (not raw files)
- ✅ **Fast Processing**: Work with local files during processing
- ✅ **No Wasted Storage**: Failed demos don't waste Backblaze storage
- ✅ **Admin Review**: Failed demos kept locally for investigation
- ✅ **Simple Paths**: Backblaze uses flat structure `demos/{filename}` - no hierarchical paths needed

### Offline vs Online Demos

**Gametype Field:**
- `df` - Offline Defrag
- `fs` - Offline Freestyle
- `fc` - Offline Fast Caps
- `mdf` - Multiplayer/Online Defrag
- `mfs` - Multiplayer/Online Freestyle
- `mfc` - Multiplayer/Online Fast Caps

**Usage:**
```php
// Check if demo is online
$demo->is_online; // true if gametype starts with 'm'

// Check if demo is offline
$demo->is_offline; // true if gametype is df/fs/fc

// Get offline demos for a map
$offlineDemos = UploadedDemo::where('map_name', 'wcp15')
    ->where('gametype', 'df')
    ->where('physics', 'VQ3')
    ->orderBy('time_ms', 'asc')
    ->get();
```

**Offline Leaderboards:**
- Offline demos are NOT assigned to records from q3df.org (which are all online records)
- Offline demos create separate leaderboards per map
- Can be displayed in map details page
- Useful for practice runs and local competitions

---

## Demo Reassignment After Records Repopulation

If you need to delete and repopulate records (e.g., rescraping from source), demos will NOT be deleted. They remain in Backblaze with their metadata in the database.

### How It Works

When demos are uploaded and processed:
1. **Demo file** is stored in Backblaze B2 (compressed .7z)
2. **Metadata** is extracted and stored in `uploaded_demos` table:
   - `map_name` - extracted from demo
   - `physics` - VQ3 or CPM
   - `time_ms` - time in milliseconds
   - `user_id` - who uploaded it
   - `processing_output` - full XML output with detailed info

When you delete records:
- Foreign key is set to `nullOnDelete()`
- Demos remain in database with `record_id = NULL`
- Demo files stay in Backblaze untouched

### Reassign Demos to New Records

After repopulating records, run the reassignment command:

```bash
# Dry run first to see what will be matched
php artisan demos:reassign --dry-run

# Reassign all demos
php artisan demos:reassign

# Only reassign unassigned demos (record_id = NULL)
php artisan demos:reassign --unassigned-only
```

**Matching Logic:**
- Matches `demo.map_name` to `record.mapname`
- Matches `demo.physics` (VQ3/CPM) to `record.gametype` (run_vq3/run_cpm)
- Matches `demo.time_ms` to `record.time`
- If demo has `user_id`, also matches to `record.user_id`

**Benefits:**
- ✅ No need to download/reupload demos
- ✅ All metadata already in database
- ✅ Demos stay in Backblaze permanently
- ✅ Can repopulate records as many times as needed
- ✅ Fast - just database queries

**Example Workflow:**
```bash
# 1. Upload demos (they get processed and stored in Backblaze)
# Users upload via /demos

# 2. Delete records and repopulate from source
php artisan records:delete-all
php artisan records:scrape-from-source

# 3. Reassign demos to new records
php artisan demos:reassign --dry-run  # Check first
php artisan demos:reassign            # Apply changes

# Done! Demos are now assigned to the new records
```

---

## Rollback Plan

If S3 has issues, quickly switch back to local storage:

```bash
# In .env
FILESYSTEM_DISK=local

# Restart services
php artisan config:cache
php artisan octane:reload
```

Your code doesn't need changes - it uses `Storage::disk()` facade which adapts automatically.
