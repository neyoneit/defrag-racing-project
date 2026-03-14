# Offline Records System

## Overview

The offline records system handles leaderboards for demos recorded in offline/practice mode (not on multiplayer servers). Unlike online records which come from q3df.org's centralized database, offline records come from user-uploaded demo files.

**Key Distinction:**
- **Online demos**: Recorded on multiplayer servers (gametypes: `mdf`, `mfs`, `mfc`)
- **Offline demos**: Recorded in practice mode (gametypes: `df`, `fs`, `fc`)

## Why Separate Table?

We chose Option 1: Separate `offline_records` table instead of adding offline records to the existing `records` table.

### Performance Benefits

1. **Fast Rank Queries**: Pre-calculated ranks stored in database
   - No need to count rows on every leaderboard request
   - Simple `WHERE rank <= 100` instead of complex `COUNT()` queries

2. **Optimized Indexes**: Composite indexes designed specifically for leaderboard queries
   ```sql
   -- Fast leaderboard retrieval
   INDEX (map_name, physics, gametype, rank)

   -- Fast time-based queries (for rank calculation)
   INDEX (map_name, physics, gametype, time_ms)
   ```

3. **Scalability**: Handles 100,000+ offline records without performance degradation
   - Separate table keeps queries focused
   - No mixing of online/offline logic
   - Can be optimized/partitioned independently

4. **Simple Queries**: Clear separation of concerns
   ```php
   // Get top 100 offline records for a map
   OfflineRecord::where('map_name', $mapName)
       ->where('physics', 'VQ3')
       ->where('gametype', 'df')
       ->where('rank', '<=', 100)
       ->orderBy('rank')
       ->get();
   ```

### Data Integrity Benefits

1. **Different Data Sources**:
   - Online records: Imported from q3df.org (authoritative, verified)
   - Offline records: User-uploaded demos (community-submitted, unverified)

2. **Different Metadata**:
   - Online records: Include server info, player accounts, official rankings
   - Offline records: Only demo file metadata, player names may be inconsistent

3. **Easier Moderation**:
   - Cheated/invalid offline records can be deleted without affecting online records
   - Admin can recalculate offline ranks independently

## Database Structure

### Table: `offline_records`

```sql
CREATE TABLE offline_records (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    map_name VARCHAR(255) NOT NULL,
    physics VARCHAR(10) NOT NULL,        -- 'VQ3' or 'CPM'
    gametype VARCHAR(10) NOT NULL,       -- 'df', 'fs', 'fc'
    time_ms INT NOT NULL,                -- Time in milliseconds
    player_name VARCHAR(255) NULL,       -- Player name from demo file
    demo_id BIGINT UNSIGNED UNIQUE,      -- FK to uploaded_demos
    rank INT DEFAULT 1,                  -- Pre-calculated rank (1 = fastest)
    date_set DATETIME NOT NULL,          -- When record was set
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (demo_id) REFERENCES uploaded_demos(id) ON DELETE CASCADE,

    INDEX idx_map (map_name),
    INDEX idx_physics (physics),
    INDEX idx_gametype (gametype),
    INDEX offline_leaderboard_idx (map_name, physics, gametype, time_ms),
    INDEX offline_rank_idx (map_name, physics, gametype, rank)
);
```

### Relationships

```
uploaded_demos (1) ---> (1) offline_records
```

- Each offline demo can have ONE offline record
- If demo is deleted, offline record is cascade deleted
- Demo stores the raw file, record stores the leaderboard entry

## Ranking System

### How Ranks are Calculated

When a new offline record is created:

1. **Count faster times** for the same map/physics/gametype
   ```php
   $fasterTimes = OfflineRecord::where('map_name', $mapName)
       ->where('physics', $physics)
       ->where('gametype', $gametype)
       ->where('time_ms', '<', $newTime)
       ->count();

   $rank = $fasterTimes + 1;
   ```

2. **Insert new record** with calculated rank

3. **Increment ranks** of all slower records
   ```php
   OfflineRecord::where('map_name', $mapName)
       ->where('physics', $physics)
       ->where('gametype', $gametype)
       ->where('time_ms', '>=', $newTime)
       ->where('id', '!=', $newRecordId)
       ->increment('rank');
   ```

### Handling Duplicate Times

- Multiple players can have the same time
- They will have different ranks (insertion order determines rank)
- Example: If two players both have 5.160, first one gets rank 1, second gets rank 2

### Recalculating Ranks

If ranks become inconsistent (e.g., after bulk import or deletion):

```php
OfflineRecord::recalculateRanks($mapName, $physics, $gametype);
```

This method:
1. Fetches all records for that map/physics/gametype ordered by time
2. Reassigns ranks sequentially (1, 2, 3, ...)

## Auto-Assignment Flow

### When Demo is Uploaded

1. User uploads `.dm_68` demo file
2. File stored temporarily in `storage/app/demos/temp/{demo_id}/`
3. Job `ProcessDemoJob` dispatched to queue

### When Demo is Processed

1. Python script extracts metadata (map, physics, gametype, time, player name)
2. Demo compressed to `.7z` format
3. Compressed file uploaded to Backblaze B2
4. Metadata saved to `uploaded_demos` table

### Auto-Assignment Logic

```php
if ($demo->is_offline) {
    // Offline demo (df, fs, fc)
    $this->createOfflineRecord($demo);
} else {
    // Online demo (mdf, mfs, mfc)
    $this->autoAssignToRecord($demo);
}
```

**Offline Record Creation:**
- Check if all required fields exist (map_name, physics, gametype, time_ms)
- Check if offline record already exists for this demo
- Calculate rank based on existing records
- Create `offline_records` entry
- Update ranks of slower records
- Log creation

**Online Record Assignment:**
- Try to match to existing `records` entry from q3df.org
- Match by: map_name, physics (converted to gametype), time_ms, user_id
- If match found, link demo to record

## Query Patterns

### Get Top 100 for a Map

```php
$leaderboard = OfflineRecord::where('map_name', 'alkatraz')
    ->where('physics', 'VQ3')
    ->where('gametype', 'df')
    ->where('rank', '<=', 100)
    ->orderBy('rank')
    ->with('demo') // Eager load demo file info
    ->get();
```

### Get Player's Best Time on Map

```php
$playerBest = OfflineRecord::where('map_name', 'alkatraz')
    ->where('physics', 'VQ3')
    ->where('gametype', 'df')
    ->where('player_name', 'LIKE', '%' . $playerName . '%')
    ->orderBy('time_ms')
    ->first();
```

### Get Records in Time Range

```php
// Records between 10-20 seconds
$records = OfflineRecord::where('map_name', 'alkatraz')
    ->where('physics', 'VQ3')
    ->where('gametype', 'df')
    ->whereBetween('time_ms', [10000, 20000])
    ->orderBy('time_ms')
    ->get();
```

### Get Recent Records

```php
$recentRecords = OfflineRecord::orderBy('date_set', 'desc')
    ->limit(50)
    ->with('demo')
    ->get();
```

## UI Design

### Map Details Page - Offline Leaderboards

The map details page should show both online records (from q3df.org) and offline records (from uploaded demos).

#### Tab Structure

```
[Online Records] [Offline Records]
```

**Online Records Tab** (existing):
- Shows records from `records` table (q3df.org data)
- Displays: Rank, Player, Time, Date, Download Demo (if available)

**Offline Records Tab** (new):
- Sub-tabs for each gametype:
  ```
  [Defrag (df)] [Freestyle (fs)] [Fast Caps (fc)]
  ```
- Each sub-tab shows leaderboard for that gametype
- Physics switcher: [VQ3] [CPM]
- Display columns:
  - Rank
  - Player Name (from demo file)
  - Time (formatted: M:SS.mmm)
  - Date Set
  - Download Demo (link to compressed .7z file)

#### Example Layout

```
┌─────────────────────────────────────────────┐
│ Map: alkatraz                               │
│ [Online Records] [Offline Records]          │
├─────────────────────────────────────────────┤
│ Offline Records                             │
│ [Defrag (df)] [Freestyle (fs)] [Fast Caps]  │
│ Physics: [VQ3] [CPM]                        │
├─────────────────────────────────────────────┤
│ Rank | Player      | Time      | Date       │
├─────────────────────────────────────────────┤
│ 1    | DefragPro   | 0:05.160  | 2025-10-26 │
│ 2    | SpeedRunner | 0:05.234  | 2025-10-25 │
│ 3    | JumpMaster  | 0:05.456  | 2025-10-24 │
│ ...                                         │
└─────────────────────────────────────────────┘
```

### Admin Panel - Offline Records Management

Add section to admin panel (`/defraghq`) for managing offline records:

**Features:**
- View all offline records with filters (map, physics, gametype)
- Delete suspicious/cheated records
- Recalculate ranks for specific map/physics/gametype
- View associated demo files
- Bulk operations

## API Endpoints

### To Be Implemented

#### GET `/api/maps/{mapName}/offline-records`

Get offline leaderboard for a map.

**Query Parameters:**
- `physics` (required): `VQ3` or `CPM`
- `gametype` (required): `df`, `fs`, or `fc`
- `limit` (optional, default: 100): Number of records to return
- `offset` (optional, default: 0): Pagination offset

**Response:**
```json
{
  "map_name": "alkatraz",
  "physics": "VQ3",
  "gametype": "df",
  "total_records": 234,
  "records": [
    {
      "rank": 1,
      "player_name": "DefragPro",
      "time_ms": 5160,
      "formatted_time": "0:05.160",
      "date_set": "2025-10-26T15:30:00Z",
      "demo_url": "https://defrag-demos.b2.com/demos/alkatraz[df.vq3]00.05.160(DefragPro).7z"
    }
  ]
}
```

#### POST `/api/offline-records/{id}/recalculate-ranks`

Admin endpoint to recalculate ranks for a specific map/physics/gametype.

**Request Body:**
```json
{
  "map_name": "alkatraz",
  "physics": "VQ3",
  "gametype": "df"
}
```

**Response:**
```json
{
  "success": true,
  "records_updated": 234
}
```

#### DELETE `/api/offline-records/{id}`

Admin endpoint to delete a record (also deletes associated demo).

**Response:**
```json
{
  "success": true,
  "message": "Offline record deleted"
}
```

## Edge Cases & Considerations

### 1. Duplicate Demos

**Problem**: User uploads same demo multiple times
**Solution**:
- Hash check prevents duplicate uploads (`file_hash` field)
- If hash matches existing demo, reject upload

### 2. Cheated/Invalid Records

**Problem**: User uploads modified/cheated demo
**Solution**:
- Admin can delete from admin panel
- Deletion cascades to demo file
- Ranks automatically recalculated for remaining records

### 3. Player Name Inconsistencies

**Problem**: Same player uses different names in different demos
**Solution**:
- Store name as-is from demo file
- Future enhancement: Player name normalization/mapping
- Future enhancement: Allow users to claim offline records

### 4. Map Name Variations

**Problem**: Same map might have different names (e.g., "alkatraz" vs "alkatraz_v2")
**Solution**:
- Use exact map name from demo file
- Separate leaderboards for different versions
- Admin can merge/rename if needed

### 5. Missing Metadata

**Problem**: Demo file corrupted or processor can't extract metadata
**Solution**:
- Demo marked as `status='failed'`
- Moved to `storage/app/demos/failed/{demo_id}/`
- Admin can review and manually process if needed

### 6. Rank Drift

**Problem**: Ranks might become inconsistent after bulk operations
**Solution**:
- Use `OfflineRecord::recalculateRanks()` to fix
- Can be run periodically via cron job
- Admin can trigger manually from panel

## Testing Plan

### 1. Test Offline Record Creation

Upload offline demo and verify:
- [ ] Demo processed successfully
- [ ] Offline record created in database
- [ ] Rank calculated correctly (1 for first record)
- [ ] No online record assignment attempted

### 2. Test Ranking Logic

Upload multiple demos for same map:
- [ ] Second demo with faster time gets rank 1
- [ ] Previous rank 1 becomes rank 2
- [ ] Third demo with slowest time gets rank 3

### 3. Test Online Demo Handling

Upload online demo (mdf/mfs/mfc):
- [ ] No offline record created
- [ ] Auto-assignment to online record attempted
- [ ] Gametype correctly identified as online

### 4. Test Query Performance

With 1000+ offline records:
- [ ] Leaderboard query under 100ms
- [ ] Rank calculation under 200ms
- [ ] Composite indexes being used (EXPLAIN query)

### 5. Test Edge Cases

- [ ] Upload demo with missing metadata
- [ ] Upload duplicate demo (same hash)
- [ ] Delete record and verify ranks recalculated
- [ ] Multiple demos with identical times

## Future Enhancements

### Phase 1 (Current)
- [x] Database structure
- [x] Auto-assignment logic
- [x] Basic rank calculation

### Phase 2 (Next)
- [ ] API endpoints
- [ ] UI for offline leaderboards on map page
- [ ] Admin panel for record management

### Phase 3 (Future)
- [ ] Player profiles for offline records
- [ ] Allow users to claim offline records
- [ ] Stats: Total offline records per player
- [ ] Offline world records page
- [ ] Compare offline vs online times

### Phase 4 (Advanced)
- [ ] Demo verification system (detect cheats)
- [ ] Player name normalization/mapping
- [ ] Map name aliases (merge variants)
- [ ] Offline record achievements/badges
- [ ] Notification when record is beaten

## Migration Path

### For Existing Demos

If demos were uploaded before offline records system:

```bash
# Command to create offline records from existing demos
php artisan demos:create-offline-records
```

This command should:
1. Find all uploaded_demos with `gametype IN ('df', 'fs', 'fc')`
2. Filter for demos with `status='processed'` and complete metadata
3. For each demo, call `createOfflineRecord()`
4. Log progress and results

### Bulk Import

For importing large batches of offline demos:

```bash
# Import demos from directory
php artisan demos:bulk-import /path/to/demos/ --offline
```

This command should:
1. Process demos in chunks (e.g., 100 at a time)
2. Use queue jobs for processing
3. Calculate ranks in batches after all imports
4. Provide progress feedback

## Performance Benchmarks

### Expected Performance (with 100,000 offline records)

| Operation | Target Time | Notes |
|-----------|-------------|-------|
| Get leaderboard (100 records) | < 100ms | Uses rank index |
| Create new record | < 200ms | Includes rank update |
| Recalculate all ranks (one map) | < 5s | Batch operation |
| Delete record | < 100ms | Cascades to demo |
| Search by player name | < 500ms | LIKE query, not indexed |

### Database Size Estimates

Assuming 100,000 offline records:

```
Columns per row:
- id: 8 bytes
- map_name: ~20 bytes avg
- physics: 3 bytes
- gametype: 2 bytes
- time_ms: 4 bytes
- player_name: ~20 bytes avg
- demo_id: 8 bytes
- rank: 4 bytes
- date_set: 8 bytes
- timestamps: 16 bytes
Total: ~93 bytes per row

100,000 rows × 93 bytes = ~9.3 MB (raw data)
+ Indexes: ~15-20 MB
Total: ~25-30 MB
```

Very manageable size, even with millions of records.

## Monitoring & Maintenance

### Metrics to Track

1. **Record Count**: Total offline records, grouped by gametype
2. **Growth Rate**: New offline records per day/week
3. **Query Performance**: Average leaderboard query time
4. **Rank Consistency**: Check for rank gaps/duplicates

### Maintenance Tasks

**Daily:**
- Monitor failed demos
- Review suspicious records (extremely fast times)

**Weekly:**
- Verify rank consistency for popular maps
- Clean up old failed demos

**Monthly:**
- Analyze query performance
- Optimize indexes if needed
- Review storage usage

## Conclusion

The offline records system provides a scalable, performant solution for handling community-uploaded demos with separate leaderboards. By pre-calculating ranks and using optimized indexes, we can serve leaderboards instantly even with hundreds of thousands of records.

The separation from online records maintains data integrity while allowing for future enhancements like player profiles, achievements, and advanced verification systems.
