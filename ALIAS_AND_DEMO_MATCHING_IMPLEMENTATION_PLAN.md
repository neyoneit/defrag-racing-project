# **Player Alias & Demo Matching System - Complete Implementation Plan**

## **Overview**

This document outlines the complete implementation plan for adding a player alias system, intelligent demo name matching, upload restrictions, demo reporting system, and admin management tools to the defrag racing project. The system will allow users to add nickname aliases, automatically match demos to players with high confidence, report incorrect assignments, and provide comprehensive admin tools for managing the entire ecosystem.

---

## **Table of Contents**

1. [Current Behavior](#current-behavior-before-changes)
2. [Desired Behavior](#desired-behavior-after-changes)
3. [User Requirements & Restrictions](#user-requirements--restrictions)
4. [Database Schema](#phase-1-database--models)
5. [Name Matching Service](#phase-2-name-matching-service)
6. [Demo Processor Updates](#phase-3-update-demo-processor)
7. [Profile Settings - Alias Management](#phase-4-profile-settings---alias-management)
8. [Profile Page - Stats & Aliases](#phase-5-profile-page---stats--aliases)
9. [Demo Reporting System](#phase-6-demo-reporting-system)
10. [Filament Admin Panel](#phase-7-filament-admin-panel)
11. [Retroactive Demo Matching](#phase-8-retroactive-demo-matching)
12. [UI Changes - Map Details](#phase-9-ui-changes---map-details-demos-top)
13. [UI Changes - Demos Section](#phase-10-ui-changes---demos-section-filters)
14. [Manual Assignment Feature](#phase-11-manual-assignment-feature)
15. [Upload & Assignment Restrictions](#phase-12-upload--assignment-restrictions)
16. [Testing Checklist](#phase-13-testing-checklist)
17. [Implementation Order](#implementation-order-summary)

---

## **Current Behavior (Before Changes)**

### Demo Processing Flow:
1. **Online demos** (gametype starts with 'm'):
   - Match by: mapname + gametype + time + user_id (uploader only)
   - If match found → assign to online record
   - If no match → mark as 'failed' ❌

2. **Offline demos** (gametype doesn't start with 'm'):
   - Always create offline_record entry
   - Upload to Backblaze
   - Mark as 'assigned'

### Upload System:
- Anyone can upload (including guests)
- No minimum record requirements
- No reporting system
- No way to reassign failed demos

### Problems:
- Online demos uploaded by logged-in users can only match their own records
- If I upload someone else's demo, it fails even if the record exists
- No way to match demos by player name
- Failed demos are just marked as failed with no recovery path
- No alias system for players with multiple nicknames
- No accountability or reporting system
- Guests can upload spam demos

---

## **Desired Behavior (After Changes)**

### Demo Processing Flow:

1. **Name Matching (Two-Pass Strategy)**:
   - **Pass 1**: If uploader exists, check demo player_name against uploader's nickname + aliases
   - **Pass 2**: If no match, check demo player_name against ALL users' nicknames + aliases
   - Strip Quake color codes before matching (^0-^9, ^[, ^])
   - Calculate confidence score (0-100%) using fuzzy matching

2. **Auto-Assignment Logic**:
   - **100% name match found** → Find matching record for that user and assign ✅
   - **<100% name match** → Create offline_record entry with confidence score + suggested_user_id
   - **No name match** → Create offline_record entry

3. **"Demos Top" Section**:
   - Combines: All offline demos + Online demos without 100% name match
   - Replaces the current "Offline Demos" section

4. **Manual Review & Assignment**:
   - Users with 30+ records can request reassignment (creates report for admin)
   - Admins can manually assign demos with <100% confidence
   - Filter demos by confidence percentage (90%, 80%, 70%, etc.)
   - See suggested user matches for review

5. **Upload Restrictions**:
   - Must be logged in (guests CANNOT upload)
   - Must have 30+ records to upload
   - Upload-restricted users cannot upload

6. **Reporting & Accountability**:
   - Users can report wrong assignments, bad demos, or request reassignments
   - All reports reviewed by admins
   - Predefined reasons for easy categorization
   - Admin can restrict users from uploading/assigning

---

## **User Requirements & Restrictions**

### Minimum Requirements:
- **Upload demos**: Must have **30 records**
- **Reassign demos**: Must have **30 records**
- **Report demos**: Must have **30 records**
- **Guest users**: CANNOT upload, assign, or report

### User Restriction Flags:
- `upload_restricted`: User cannot upload new demos
- `assignment_restricted`: User cannot manually assign/reassign demos
- `alias_restricted`: User cannot create new aliases (must request approval)

### Admin Powers:
- Can override all restrictions
- Can bulk delete user's demos
- Can bulk remove user's assignments
- Can restrict/unrestrict users
- Can approve/reject reports and reassignment requests

---

## **Phase 1: Database & Models**

### 1.1 Create `user_aliases` Table

```php
// Migration: create_user_aliases_table.php
Schema::create('user_aliases', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('alias')->unique(); // Must be unique across entire system
    $table->boolean('is_approved')->default(true); // False if user is restricted
    $table->timestamps();

    $table->index(['user_id', 'is_approved']);
});
```

### 1.2 Create `alias_reports` Table

```php
// Migration: create_alias_reports_table.php
Schema::create('alias_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('alias_id')->constrained('user_aliases')->onDelete('cascade');
    $table->foreignId('reported_by_user_id')->constrained('users')->onDelete('cascade');
    $table->text('reason');
    $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
    $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users');
    $table->timestamp('resolved_at')->nullable();
    $table->text('admin_notes')->nullable();
    $table->timestamps();

    $table->index('status');
});
```

### 1.3 Create `demo_assignment_reports` Table

```php
// Migration: create_demo_assignment_reports_table.php
Schema::create('demo_assignment_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('demo_id')->constrained('uploaded_demos')->onDelete('cascade');
    $table->enum('report_type', ['reassignment_request', 'wrong_assignment', 'bad_demo']);
    $table->foreignId('reported_by_user_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('current_record_id')->nullable()->constrained('records')->onDelete('set null');
    $table->foreignId('suggested_record_id')->nullable()->constrained('records')->onDelete('set null');
    $table->string('reason_type'); // Predefined reason selected
    $table->text('reason_details')->nullable(); // Optional additional details
    $table->enum('status', ['pending', 'approved', 'rejected', 'resolved'])->default('pending');
    $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users');
    $table->timestamp('resolved_at')->nullable();
    $table->text('admin_notes')->nullable();
    $table->timestamps();

    $table->index(['status', 'report_type']);
    $table->index('demo_id');
});
```

### 1.4 Add Columns to `users` Table

```php
// Migration: add_restrictions_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('upload_restricted')->default(false);
    $table->boolean('assignment_restricted')->default(false);
    $table->boolean('alias_restricted')->default(false);

    $table->index(['upload_restricted', 'assignment_restricted']);
});
```

### 1.5 Add Columns to `uploaded_demos` Table

```php
// Migration: add_name_matching_to_uploaded_demos_table.php
Schema::table('uploaded_demos', function (Blueprint $table) {
    $table->integer('name_confidence')->nullable(); // 0-100
    $table->foreignId('suggested_user_id')->nullable()->constrained('users')->onDelete('set null');
    $table->boolean('manually_assigned')->default(false);
    $table->integer('download_count')->default(0); // Track downloads for stats

    $table->index('name_confidence');
    $table->index('download_count');
});
```

### 1.6 Create Models

**UserAlias Model** (`app/Models/UserAlias.php`):
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAlias extends Model
{
    protected $fillable = ['user_id', 'alias', 'is_approved'];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reports()
    {
        return $this->hasMany(AliasReport::class, 'alias_id');
    }
}
```

**AliasReport Model** (`app/Models/AliasReport.php`):
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliasReport extends Model
{
    protected $fillable = [
        'alias_id',
        'reported_by_user_id',
        'reason',
        'status',
        'resolved_by_admin_id',
        'resolved_at',
        'admin_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function alias()
    {
        return $this->belongsTo(UserAlias::class, 'alias_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }
}
```

**DemoAssignmentReport Model** (`app/Models/DemoAssignmentReport.php`):
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoAssignmentReport extends Model
{
    protected $fillable = [
        'demo_id',
        'report_type',
        'reported_by_user_id',
        'current_record_id',
        'suggested_record_id',
        'reason_type',
        'reason_details',
        'status',
        'resolved_by_admin_id',
        'resolved_at',
        'admin_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function demo()
    {
        return $this->belongsTo(UploadedDemo::class, 'demo_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function currentRecord()
    {
        return $this->belongsTo(Record::class, 'current_record_id');
    }

    public function suggestedRecord()
    {
        return $this->belongsTo(Record::class, 'suggested_record_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by_admin_id');
    }
}
```

**Update User Model** (`app/Models/User.php`):
```php
// Add to User model

public function aliases()
{
    return $this->hasMany(UserAlias::class);
}

public function uploadedDemos()
{
    return $this->hasMany(UploadedDemo::class, 'user_id');
}

public function topDownloadedDemos()
{
    return $this->uploadedDemos()
        ->orderBy('download_count', 'desc')
        ->limit(5);
}

// Helper method to check if user meets requirements
public function canUploadDemos()
{
    if ($this->upload_restricted) {
        return false;
    }

    // Must have at least 30 records
    return $this->records()->count() >= 30;
}

public function canAssignDemos()
{
    if ($this->assignment_restricted) {
        return false;
    }

    // Must have at least 30 records
    return $this->records()->count() >= 30;
}

public function canReportDemos()
{
    // Must have at least 30 records
    return $this->records()->count() >= 30;
}
```

**Update UploadedDemo Model** (`app/Models/UploadedDemo.php`):
```php
// Add to UploadedDemo model

public function assignmentReports()
{
    return $this->hasMany(DemoAssignmentReport::class, 'demo_id');
}

public function suggestedUser()
{
    return $this->belongsTo(User::class, 'suggested_user_id');
}

// Increment download counter
public function incrementDownloads()
{
    $this->increment('download_count');
}
```

---

## **Phase 2: Name Matching Service**

### 2.1 Create NameMatcher Service

**File**: `app/Services/NameMatcher.php`

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class NameMatcher
{
    /**
     * Strip Quake 3 color codes from string
     * Handles: ^0-^9, ^[, ^]
     *
     * @param string $text
     * @return string
     */
    public static function stripQuakeColors(string $text): string
    {
        return preg_replace('/\^[\[\]0-9]/', '', $text);
    }

    /**
     * Calculate similarity percentage between two strings
     * Uses Levenshtein distance for fuzzy matching
     *
     * @param string $name1
     * @param string $name2
     * @return int Confidence score 0-100
     */
    public static function calculateConfidence(string $name1, string $name2): int
    {
        // Strip Quake colors and normalize
        $name1 = strtolower(self::stripQuakeColors(trim($name1)));
        $name2 = strtolower(self::stripQuakeColors(trim($name2)));

        // Exact match
        if ($name1 === $name2) {
            return 100;
        }

        // Empty strings
        if (empty($name1) || empty($name2)) {
            return 0;
        }

        // Calculate Levenshtein distance
        $distance = levenshtein($name1, $name2);
        $maxLength = max(strlen($name1), strlen($name2));

        if ($maxLength === 0) {
            return 0;
        }

        // Convert distance to similarity percentage
        $similarity = (1 - ($distance / $maxLength)) * 100;

        return max(0, min(100, (int)round($similarity)));
    }

    /**
     * Find best matching user for a demo player name
     * Two-pass strategy: uploader first, then global
     *
     * @param string $playerName - Demo player name (may contain Quake colors)
     * @param int|null $uploaderId - ID of user who uploaded the demo
     * @return array ['user_id' => int|null, 'confidence' => int, 'source' => 'uploader'|'global'|null]
     */
    public static function findBestMatch(string $playerName, ?int $uploaderId = null): array
    {
        $bestMatch = [
            'user_id' => null,
            'confidence' => 0,
            'source' => null,
        ];

        if (empty(trim($playerName))) {
            return $bestMatch;
        }

        Log::info('NameMatcher: Starting name matching', [
            'player_name' => $playerName,
            'uploader_id' => $uploaderId,
        ]);

        // Pass 1: Check uploader first (priority match)
        if ($uploaderId) {
            $uploader = User::with(['aliases' => function($q) {
                $q->where('is_approved', true);
            }])->find($uploaderId);

            if ($uploader) {
                // Check against uploader's nickname
                $confidence = self::calculateConfidence($playerName, $uploader->name);
                if ($confidence > $bestMatch['confidence']) {
                    $bestMatch = [
                        'user_id' => $uploader->id,
                        'confidence' => $confidence,
                        'source' => 'uploader',
                    ];

                    Log::debug('NameMatcher: Uploader nickname match', [
                        'user_id' => $uploader->id,
                        'username' => $uploader->name,
                        'confidence' => $confidence,
                    ]);
                }

                // Check against uploader's aliases
                foreach ($uploader->aliases as $alias) {
                    $confidence = self::calculateConfidence($playerName, $alias->alias);
                    if ($confidence > $bestMatch['confidence']) {
                        $bestMatch = [
                            'user_id' => $uploader->id,
                            'confidence' => $confidence,
                            'source' => 'uploader',
                        ];

                        Log::debug('NameMatcher: Uploader alias match', [
                            'user_id' => $uploader->id,
                            'alias' => $alias->alias,
                            'confidence' => $confidence,
                        ]);
                    }
                }

                // If 100% match found with uploader, return immediately
                if ($bestMatch['confidence'] === 100) {
                    Log::info('NameMatcher: 100% match with uploader', $bestMatch);
                    return $bestMatch;
                }
            }
        }

        // Pass 2: Check all users globally
        Log::info('NameMatcher: Starting global user search');

        $users = User::with(['aliases' => function($q) {
            $q->where('is_approved', true);
        }])->get();

        foreach ($users as $user) {
            // Skip uploader if already checked
            if ($uploaderId && $user->id === $uploaderId) {
                continue;
            }

            // Check against user's nickname
            $confidence = self::calculateConfidence($playerName, $user->name);
            if ($confidence > $bestMatch['confidence']) {
                $bestMatch = [
                    'user_id' => $user->id,
                    'confidence' => $confidence,
                    'source' => 'global',
                ];

                Log::debug('NameMatcher: Global nickname match', [
                    'user_id' => $user->id,
                    'username' => $user->name,
                    'confidence' => $confidence,
                ]);
            }

            // Check against user's aliases
            foreach ($user->aliases as $alias) {
                $confidence = self::calculateConfidence($playerName, $alias->alias);
                if ($confidence > $bestMatch['confidence']) {
                    $bestMatch = [
                        'user_id' => $user->id,
                        'confidence' => $confidence,
                        'source' => 'global',
                    ];

                    Log::debug('NameMatcher: Global alias match', [
                        'user_id' => $user->id,
                        'alias' => $alias->alias,
                        'confidence' => $confidence,
                    ]);
                }
            }

            // If 100% match found, return immediately
            if ($bestMatch['confidence'] === 100) {
                Log::info('NameMatcher: 100% match with global user', $bestMatch);
                return $bestMatch;
            }
        }

        Log::info('NameMatcher: Best match found', $bestMatch);
        return $bestMatch;
    }
}
```

---

## **Phase 3: Update Demo Processor**

### 3.1 Modify `autoAssignToRecord()` Method

**File**: `app/Services/DemoProcessorService.php`

**Current location**: Lines 389-470

**Complete replacement**:

```php
/**
 * Try to automatically assign demo to a record
 * Uses name matching with two-pass strategy
 */
protected function autoAssignToRecord(UploadedDemo $demo, $compressedLocalPath)
{
    if (!$demo->map_name || !$demo->physics || !$demo->time_ms || !$demo->player_name) {
        Log::warning('Cannot auto-assign demo - missing required fields', [
            'demo_id' => $demo->id,
            'map_name' => $demo->map_name,
            'physics' => $demo->physics,
            'time_ms' => $demo->time_ms,
            'player_name' => $demo->player_name,
        ]);
        return;
    }

    // IMPORTANT: Only process ONLINE demos here
    // Offline demos should use createOfflineRecord()
    if ($demo->gametype && !str_starts_with($demo->gametype, 'm')) {
        Log::info('Skipping auto-assign for offline demo', [
            'demo_id' => $demo->id,
            'gametype' => $demo->gametype,
            'map' => $demo->map_name,
        ]);
        return;
    }

    // NEW: Use NameMatcher to find best matching user
    $nameMatch = NameMatcher::findBestMatch($demo->player_name, $demo->user_id);

    // Store name matching results
    $demo->update([
        'name_confidence' => $nameMatch['confidence'],
        'suggested_user_id' => $nameMatch['user_id'],
    ]);

    Log::info('Name matching result', [
        'demo_id' => $demo->id,
        'player_name' => $demo->player_name,
        'matched_user_id' => $nameMatch['user_id'],
        'confidence' => $nameMatch['confidence'],
        'source' => $nameMatch['source'],
    ]);

    // Only auto-assign if 100% name confidence
    if ($nameMatch['confidence'] < 100) {
        Log::info('Name confidence < 100%, creating offline record instead', [
            'demo_id' => $demo->id,
            'confidence' => $nameMatch['confidence'],
        ]);

        // Create offline record for review (goes to "Demos Top")
        $this->createOfflineRecord($demo, $compressedLocalPath);
        return;
    }

    // 100% name match found - try to assign to online record
    $matchedUserId = $nameMatch['user_id'];

    // Build gametype string (e.g., "run_cpm" or "run_vq3")
    $physics = str_replace('.tr', '', strtolower($demo->physics));
    $gametype = 'run_' . $physics;

    // Find matching record for the matched user
    $record = Record::where('mapname', $demo->map_name)
        ->where('gametype', $gametype)
        ->where('time', $demo->time_ms)
        ->where('user_id', $matchedUserId)
        ->first();

    if ($record) {
        // Match found! Upload to Backblaze
        $uploadedPath = $this->uploadToBackblaze($compressedLocalPath, $demo->processed_filename);

        $demo->update([
            'record_id' => $record->id,
            'status' => 'assigned',
            'file_path' => $uploadedPath,
        ]);

        // Clean up local compressed file after successful upload
        if (file_exists($compressedLocalPath)) {
            unlink($compressedLocalPath);
        }

        Log::info('Demo auto-assigned to record (100% name match)', [
            'demo_id' => $demo->id,
            'record_id' => $record->id,
            'matched_user_id' => $matchedUserId,
            'match_source' => $nameMatch['source'],
        ]);
    } else {
        // 100% name match but no matching record found
        // Create offline record for "Demos Top"
        Log::info('100% name match but no record found, creating offline record', [
            'demo_id' => $demo->id,
            'matched_user_id' => $matchedUserId,
            'map' => $demo->map_name,
            'gametype' => $gametype,
            'time_ms' => $demo->time_ms,
        ]);

        $this->createOfflineRecord($demo, $compressedLocalPath);
    }
}
```

---

## **Phase 4: Profile Settings - Alias Management**

### 4.1 Create Alias Controller

**File**: `app/Http/Controllers/AliasController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\UserAlias;
use App\Jobs\RematchDemosByAlias;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AliasController extends Controller
{
    /**
     * Store a new alias
     */
    public function store(Request $request)
    {
        $request->validate([
            'alias' => 'required|string|max:255|unique:user_aliases,alias',
        ]);

        $user = Auth::user();

        // Check if user has reached alias limit
        if ($user->aliases()->count() >= 10) {
            return back()->with('danger', 'Maximum 10 aliases allowed per account.');
        }

        // Check if user is restricted
        if ($user->alias_restricted) {
            // Create pending alias request
            $alias = UserAlias::create([
                'user_id' => $user->id,
                'alias' => $request->alias,
                'is_approved' => false,
            ]);

            return back()->with('success', 'Alias request submitted for admin approval.');
        }

        // Create approved alias
        $alias = UserAlias::create([
            'user_id' => $user->id,
            'alias' => $request->alias,
            'is_approved' => true,
        ]);

        // Trigger retroactive demo matching
        dispatch(new RematchDemosByAlias($alias));

        return back()->with('success', 'Alias added successfully! Checking existing demos for matches...');
    }

    /**
     * Delete an alias
     */
    public function destroy(UserAlias $alias)
    {
        if ($alias->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $alias->delete();

        return back()->with('success', 'Alias deleted.');
    }
}
```

### 4.2 Create Alias Report Controller

**File**: `app/Http/Controllers/AliasReportController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\UserAlias;
use App\Models\AliasReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AliasReportController extends Controller
{
    /**
     * Report an alias as false/incorrect
     */
    public function store(Request $request, UserAlias $alias)
    {
        $user = Auth::user();

        // Check if user meets requirements
        if (!$user->canReportDemos()) {
            return back()->with('danger', 'You need at least 30 records to report aliases.');
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Check if user already reported this alias
        $existing = AliasReport::where('alias_id', $alias->id)
            ->where('reported_by_user_id', $user->id)
            ->exists();

        if ($existing) {
            return back()->with('warning', 'You have already reported this alias.');
        }

        AliasReport::create([
            'alias_id' => $alias->id,
            'reported_by_user_id' => $user->id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Alias reported. Admins will review it.');
    }
}
```

### 4.3 Add Routes

**File**: `routes/web.php`

```php
// Alias management routes
Route::middleware('auth')->group(function () {
    Route::post('/aliases', [AliasController::class, 'store'])->name('aliases.store');
    Route::delete('/aliases/{alias}', [AliasController::class, 'destroy'])->name('aliases.destroy');
    Route::post('/aliases/{alias}/report', [AliasReportController::class, 'store'])->name('aliases.report');
});
```

### 4.4 Update Profile Settings Vue Component

**File**: `resources/js/Pages/Profile/Settings.vue` (or create if doesn't exist)

**Add Alias Management Section**:

```vue
<template>
    <!-- ... existing profile settings ... -->

    <!-- Alias Management Section -->
    <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <h2 class="text-xl font-bold text-white mb-4">Player Aliases</h2>
        <p class="text-gray-400 text-sm mb-4">
            Add alternative nicknames you've used. This helps match demos to your account automatically.
            Maximum 10 aliases. {{ user.alias_restricted ? 'Your account is restricted - aliases require admin approval.' : '' }}
        </p>

        <!-- Current Aliases -->
        <div v-if="aliases.length > 0" class="mb-4 space-y-2">
            <div v-for="alias in aliases" :key="alias.id"
                 class="flex items-center justify-between bg-gray-700/50 rounded-lg p-3">
                <div class="flex items-center gap-3">
                    <span class="text-white">{{ alias.alias }}</span>
                    <span v-if="!alias.is_approved"
                          class="text-xs bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded">
                        Pending Approval
                    </span>
                </div>
                <button @click="deleteAlias(alias.id)"
                        class="text-red-400 hover:text-red-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Add New Alias -->
        <form @submit.prevent="addAlias" v-if="aliases.length < 10" class="flex gap-2">
            <input
                v-model="newAlias"
                type="text"
                placeholder="Enter alias name"
                maxlength="255"
                class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none"
                required
            />
            <button
                type="submit"
                class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-lg transition-colors font-semibold"
            >
                Add Alias
            </button>
        </form>

        <p v-else class="text-gray-400 text-sm">
            Maximum aliases reached (10/10)
        </p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
    aliases: Array,
});

const newAlias = ref('');

const addAlias = () => {
    router.post(route('aliases.store'), {
        alias: newAlias.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newAlias.value = '';
        },
    });
};

const deleteAlias = (aliasId) => {
    if (confirm('Are you sure you want to delete this alias?')) {
        router.delete(route('aliases.destroy', aliasId), {
            preserveScroll: true,
        });
    }
};
</script>
```

---

## **Phase 5: Profile Page - Stats & Aliases**

### 5.1 Update Profile Controller

**File**: `app/Http/Controllers/ProfileController.php`

```php
public function show($username)
{
    $user = User::where('name', $username)
        ->with([
            'aliases' => function($q) {
                $q->where('is_approved', true);
            },
            'topDownloadedDemos.record',
            'topDownloadedDemos.offlineRecord',
        ])
        ->firstOrFail();

    // ... rest of profile data

    return Inertia::render('Profile', [
        'user' => $user,
        'aliases' => $user->aliases,
        'topDownloadedDemos' => $user->topDownloadedDemos,
        // ... other data
    ]);
}
```

### 5.2 Update Profile Vue Component

**File**: `resources/js/Pages/Profile.vue`

**Add sections**:

```vue
<template>
    <!-- ... existing profile content ... -->

    <!-- Top Downloaded Demos Section -->
    <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <h2 class="text-xl font-bold text-white mb-4">
            <svg class="w-6 h-6 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
            </svg>
            Top Downloaded Demos
        </h2>

        <div v-if="topDownloadedDemos && topDownloadedDemos.length > 0" class="space-y-3">
            <Link
                v-for="demo in topDownloadedDemos"
                :key="demo.id"
                :href="`/maps/${encodeURIComponent(demo.map_name)}`"
                class="flex items-center justify-between bg-gray-700/50 hover:bg-gray-700 rounded-lg p-4 transition-colors group"
            >
                <div class="flex-1">
                    <div class="text-white font-semibold">{{ demo.processed_filename || demo.original_filename }}</div>
                    <div class="text-sm text-gray-400 mt-1">
                        {{ demo.map_name }} • {{ formatTime(demo.time_ms) }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-blue-400 font-bold">{{ demo.download_count }}</div>
                    <div class="text-xs text-gray-500">downloads</div>
                </div>
            </Link>
        </div>

        <div v-else class="text-gray-400 text-center py-8">
            No demos uploaded yet
        </div>
    </div>

    <!-- Player Aliases Section -->
    <div v-if="aliases && aliases.length > 0" class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <h2 class="text-xl font-bold text-white mb-4">
            <svg class="w-6 h-6 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
            </svg>
            Known Aliases
        </h2>

        <div class="flex flex-wrap gap-2">
            <div
                v-for="alias in aliases"
                :key="alias.id"
                class="bg-gray-700/50 rounded-lg px-4 py-2 flex items-center gap-2"
            >
                <span class="text-white">{{ alias.alias }}</span>

                <!-- Report button (only show for other users viewing profile) -->
                <button
                    v-if="$page.props.auth.user && $page.props.auth.user.id !== user.id"
                    @click="reportAlias(alias)"
                    class="text-gray-400 hover:text-red-400 transition-colors"
                    title="Report this alias"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
    aliases: Array,
    topDownloadedDemos: Array,
    // ... other props
});

const showReportModal = ref(false);
const reportingAlias = ref(null);
const reportReason = ref('');

const reportAlias = (alias) => {
    reportingAlias.value = alias;
    showReportModal.value = true;
};

const submitAliasReport = () => {
    router.post(route('aliases.report', reportingAlias.value.id), {
        reason: reportReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showReportModal.value = false;
            reportReason.value = '';
            reportingAlias.value = null;
        },
    });
};

const formatTime = (ms) => {
    if (!ms) return '-';
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    const milliseconds = ms % 1000;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}.${milliseconds.toString().padStart(3, '0')}`;
};
</script>
```

---

## **Phase 6: Demo Reporting System**

### 6.1 Create Demo Report Controller

**File**: `app/Http/Controllers/DemoReportController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\UploadedDemo;
use App\Models\DemoAssignmentReport;
use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoReportController extends Controller
{
    /**
     * Predefined report reasons
     */
    const REASSIGNMENT_REASONS = [
        'wrong_player' => 'Wrong player assigned',
        'better_match' => 'Better match found',
        'time_mismatch' => 'Time/physics mismatch',
        'other' => 'Other',
    ];

    const WRONG_ASSIGNMENT_REASONS = [
        'wrong_player' => 'Wrong player - name doesn\'t match',
        'wrong_map' => 'Wrong map',
        'wrong_time' => 'Wrong time',
        'duplicate' => 'Duplicate demo',
        'cheated' => 'Cheated/Modified demo',
        'other' => 'Other',
    ];

    const BAD_DEMO_REASONS = [
        'corrupted' => 'Corrupted demo file',
        'fake' => 'Fake/modified demo',
        'spam' => 'Spam upload',
        'inappropriate' => 'Inappropriate content',
        'duplicate' => 'Duplicate of existing demo',
        'other' => 'Other',
    ];

    /**
     * Submit a demo report
     */
    public function store(Request $request, UploadedDemo $demo)
    {
        $user = Auth::user();

        // Check if user meets requirements (30 records minimum)
        if (!$user->canReportDemos()) {
            return back()->with('danger', 'You need at least 30 records to report demos.');
        }

        $request->validate([
            'report_type' => 'required|in:reassignment_request,wrong_assignment,bad_demo',
            'reason_type' => 'required|string',
            'reason_details' => 'nullable|string|max:1000',
            'suggested_record_id' => 'nullable|exists:records,id',
        ]);

        // For reassignment requests, check if user can assign demos
        if ($request->report_type === 'reassignment_request') {
            if (!$user->canAssignDemos()) {
                return back()->with('danger', 'You need at least 30 records and cannot be restricted to request reassignments.');
            }

            if (!$request->suggested_record_id) {
                return back()->with('danger', 'Please select a record to assign this demo to.');
            }
        }

        // Check if user already reported this demo recently
        $recentReport = DemoAssignmentReport::where('demo_id', $demo->id)
            ->where('reported_by_user_id', $user->id)
            ->where('created_at', '>', now()->subDays(7))
            ->exists();

        if ($recentReport) {
            return back()->with('warning', 'You have already reported this demo recently.');
        }

        // Create the report
        $report = DemoAssignmentReport::create([
            'demo_id' => $demo->id,
            'report_type' => $request->report_type,
            'reported_by_user_id' => $user->id,
            'current_record_id' => $demo->record_id,
            'suggested_record_id' => $request->suggested_record_id,
            'reason_type' => $request->reason_type,
            'reason_details' => $request->reason_details,
            'status' => 'pending',
        ]);

        $messages = [
            'reassignment_request' => 'Reassignment request submitted for admin review.',
            'wrong_assignment' => 'Wrong assignment reported. Admins will investigate.',
            'bad_demo' => 'Demo reported. Admins will review it.',
        ];

        return back()->with('success', $messages[$request->report_type]);
    }
}
```

### 6.2 Add Route

**File**: `routes/web.php`

```php
// Demo reporting route
Route::middleware('auth')->group(function () {
    Route::post('/demos/{demo}/report', [DemoReportController::class, 'store'])->name('demos.report');
});
```

### 6.3 Create Report Modal Component

**File**: `resources/js/Components/DemoReportModal.vue`

```vue
<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/70" @click="$emit('close')"></div>

            <!-- Modal -->
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-gray-800 rounded-xl shadow-2xl max-w-2xl w-full border border-gray-700">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-700">
                        <h3 class="text-xl font-bold text-white">Report Demo</h3>
                        <p class="text-sm text-gray-400 mt-1">{{ demo.processed_filename || demo.original_filename }}</p>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submitReport" class="px-6 py-4 space-y-4">
                        <!-- Report Type Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-300 mb-2">Report Type</label>
                            <div class="space-y-2">
                                <!-- Reassignment Request -->
                                <label class="flex items-start gap-3 bg-gray-700/50 p-4 rounded-lg cursor-pointer hover:bg-gray-700 transition-colors">
                                    <input
                                        type="radio"
                                        v-model="form.report_type"
                                        value="reassignment_request"
                                        class="mt-1"
                                    />
                                    <div>
                                        <div class="text-white font-semibold">Reassign Demo</div>
                                        <div class="text-sm text-gray-400">Request to assign this demo to a different record</div>
                                    </div>
                                </label>

                                <!-- Wrong Assignment -->
                                <label class="flex items-start gap-3 bg-gray-700/50 p-4 rounded-lg cursor-pointer hover:bg-gray-700 transition-colors">
                                    <input
                                        type="radio"
                                        v-model="form.report_type"
                                        value="wrong_assignment"
                                        class="mt-1"
                                    />
                                    <div>
                                        <div class="text-white font-semibold">Report Wrong Assignment</div>
                                        <div class="text-sm text-gray-400">This demo is assigned to the wrong player/record</div>
                                    </div>
                                </label>

                                <!-- Bad Demo -->
                                <label class="flex items-start gap-3 bg-gray-700/50 p-4 rounded-lg cursor-pointer hover:bg-gray-700 transition-colors">
                                    <input
                                        type="radio"
                                        v-model="form.report_type"
                                        value="bad_demo"
                                        class="mt-1"
                                    />
                                    <div>
                                        <div class="text-white font-semibold">Report Bad Demo</div>
                                        <div class="text-sm text-gray-400">Demo is corrupted, fake, spam, or inappropriate</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Reassignment: Record Selection -->
                        <div v-if="form.report_type === 'reassignment_request'">
                            <label class="block text-sm font-semibold text-gray-300 mb-2">
                                Assign to Record <span class="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                v-model="recordSearch"
                                @input="searchRecords"
                                placeholder="Search for record (map name, player, time...)"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none"
                            />

                            <!-- Record Search Results -->
                            <div v-if="searchResults.length > 0" class="mt-2 bg-gray-700 rounded-lg border border-gray-600 max-h-48 overflow-y-auto">
                                <button
                                    v-for="record in searchResults"
                                    :key="record.id"
                                    type="button"
                                    @click="selectRecord(record)"
                                    class="w-full text-left px-4 py-3 hover:bg-gray-600 transition-colors border-b border-gray-600 last:border-0"
                                >
                                    <div class="text-white font-semibold">{{ record.user?.name }}</div>
                                    <div class="text-sm text-gray-400">{{ record.mapname }} • {{ formatTime(record.time) }}</div>
                                </button>
                            </div>

                            <!-- Selected Record -->
                            <div v-if="selectedRecord" class="mt-2 bg-blue-500/10 border border-blue-500/50 rounded-lg p-3">
                                <div class="text-sm text-gray-400">Selected Record:</div>
                                <div class="text-white font-semibold">{{ selectedRecord.user?.name }} - {{ selectedRecord.mapname }}</div>
                                <div class="text-sm text-gray-400">{{ formatTime(selectedRecord.time) }}</div>
                            </div>
                        </div>

                        <!-- Reason Selection -->
                        <div v-if="form.report_type">
                            <label class="block text-sm font-semibold text-gray-300 mb-2">
                                Reason <span class="text-red-400">*</span>
                            </label>
                            <select
                                v-model="form.reason_type"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none"
                                required
                            >
                                <option value="">Select a reason...</option>
                                <option
                                    v-for="(label, value) in getReasonOptions()"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <!-- Additional Details -->
                        <div v-if="form.report_type">
                            <label class="block text-sm font-semibold text-gray-300 mb-2">
                                Additional Details (Optional)
                            </label>
                            <textarea
                                v-model="form.reason_details"
                                rows="3"
                                maxlength="1000"
                                placeholder="Provide any additional information that might help..."
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none resize-none"
                            ></textarea>
                            <div class="text-xs text-gray-500 mt-1">{{ form.reason_details?.length || 0 }}/1000</div>
                        </div>
                    </form>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-700 flex justify-end gap-3">
                        <button
                            @click="$emit('close')"
                            type="button"
                            class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            @click="submitReport"
                            type="button"
                            :disabled="!canSubmit"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Submit Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    demo: Object,
});

const emit = defineEmits(['close']);

const form = ref({
    report_type: '',
    reason_type: '',
    reason_details: '',
    suggested_record_id: null,
});

const recordSearch = ref('');
const searchResults = ref([]);
const selectedRecord = ref(null);

const REASSIGNMENT_REASONS = {
    'wrong_player': 'Wrong player assigned',
    'better_match': 'Better match found',
    'time_mismatch': 'Time/physics mismatch',
    'other': 'Other',
};

const WRONG_ASSIGNMENT_REASONS = {
    'wrong_player': 'Wrong player - name doesn\'t match',
    'wrong_map': 'Wrong map',
    'wrong_time': 'Wrong time',
    'duplicate': 'Duplicate demo',
    'cheated': 'Cheated/Modified demo',
    'other': 'Other',
};

const BAD_DEMO_REASONS = {
    'corrupted': 'Corrupted demo file',
    'fake': 'Fake/modified demo',
    'spam': 'Spam upload',
    'inappropriate': 'Inappropriate content',
    'duplicate': 'Duplicate of existing demo',
    'other': 'Other',
};

const getReasonOptions = () => {
    if (form.value.report_type === 'reassignment_request') return REASSIGNMENT_REASONS;
    if (form.value.report_type === 'wrong_assignment') return WRONG_ASSIGNMENT_REASONS;
    if (form.value.report_type === 'bad_demo') return BAD_DEMO_REASONS;
    return {};
};

const canSubmit = computed(() => {
    if (!form.value.report_type || !form.value.reason_type) return false;
    if (form.value.report_type === 'reassignment_request' && !selectedRecord.value) return false;
    return true;
});

const searchRecords = () => {
    if (recordSearch.value.length < 2) {
        searchResults.value = [];
        return;
    }

    // Make API call to search records
    axios.get('/api/records/search', {
        params: { q: recordSearch.value }
    }).then(response => {
        searchResults.value = response.data;
    });
};

const selectRecord = (record) => {
    selectedRecord.value = record;
    form.value.suggested_record_id = record.id;
    searchResults.value = [];
    recordSearch.value = '';
};

const submitReport = () => {
    router.post(route('demos.report', props.demo.id), form.value, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            // Reset form
            form.value = {
                report_type: '',
                reason_type: '',
                reason_details: '',
                suggested_record_id: null,
            };
            selectedRecord.value = null;
        },
    });
};

const formatTime = (ms) => {
    if (!ms) return '-';
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    const milliseconds = ms % 1000;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}.${milliseconds.toString().padStart(3, '0')}`;
};
</script>
```

---

## **Phase 7: Filament Admin Panel**

### 7.1 Create UserAlias Filament Resource

**File**: `app/Filament/Resources/UserAliasResource.php`

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserAliasResource\Pages;
use App\Models\UserAlias;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserAliasResource extends Resource
{
    protected static ?string $model = UserAlias::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'User Aliases';
    protected static ?string $navigationGroup = 'Demo Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('alias')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_approved')
                    ->label('Approved')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('alias')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean(),
                Tables\Columns\TextColumn::make('reports_count')
                    ->counts('reports')
                    ->label('Reports'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_approved')
                    ->options([
                        1 => 'Approved',
                        0 => 'Pending',
                    ]),
                Tables\Filters\Filter::make('has_reports')
                    ->query(fn ($query) => $query->has('reports')),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (UserAlias $record) => !$record->is_approved)
                    ->action(function (UserAlias $record) {
                        $record->update(['is_approved' => true]);
                        \Illuminate\Support\Facades\Notification::make()
                            ->title('Alias approved')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_approved' => true])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserAliases::route('/'),
            'create' => Pages\CreateUserAlias::route('/create'),
            'edit' => Pages\EditUserAlias::route('/{record}/edit'),
        ];
    }
}
```

### 7.2 Create AliasReport Filament Resource

**File**: `app/Filament/Resources/AliasReportResource.php`

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AliasReportResource\Pages;
use App\Models\AliasReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AliasReportResource extends Resource
{
    protected static ?string $model = AliasReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationLabel = 'Alias Reports';
    protected static ?string $navigationGroup = 'Demo Management';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('alias.alias')
                    ->label('Reported Alias')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('alias.user.name')
                    ->label('Alias Owner')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reported By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(50),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'resolved',
                        'danger' => 'dismissed',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AliasReport $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes'),
                        Forms\Components\Toggle::make('delete_alias')
                            ->label('Delete the reported alias'),
                    ])
                    ->action(function (AliasReport $record, array $data) {
                        if ($data['delete_alias'] ?? false) {
                            $record->alias->delete();
                        }
                        $record->update([
                            'status' => 'resolved',
                            'resolved_by_admin_id' => auth()->id(),
                            'resolved_at' => now(),
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);
                    }),
                Tables\Actions\Action::make('dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AliasReport $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes'),
                    ])
                    ->action(function (AliasReport $record, array $data) {
                        $record->update([
                            'status' => 'dismissed',
                            'resolved_by_admin_id' => auth()->id(),
                            'resolved_at' => now(),
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAliasReports::route('/'),
        ];
    }
}
```

### 7.3 Create DemoAssignmentReport Filament Resource

**File**: `app/Filament/Resources/DemoAssignmentReportResource.php`

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DemoAssignmentReportResource\Pages;
use App\Models\DemoAssignmentReport;
use App\Services\DemoProcessorService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DemoAssignmentReportResource extends Resource
{
    protected static ?string $model = DemoAssignmentReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationLabel = 'Demo Reports';
    protected static ?string$navigationGroup = 'Demo Management';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\BadgeColumn::make('report_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'reassignment_request' => 'Reassignment',
                        'wrong_assignment' => 'Wrong Assignment',
                        'bad_demo' => 'Bad Demo',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'reassignment_request',
                        'warning' => 'wrong_assignment',
                        'danger' => 'bad_demo',
                    ]),
                Tables\Columns\TextColumn::make('demo.original_filename')
                    ->label('Demo')
                    ->limit(30),
                Tables\Columns\TextColumn::make('demo.map_name')
                    ->label('Map'),
                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Reported By'),
                Tables\Columns\TextColumn::make('reason_type')
                    ->label('Reason'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'secondary' => 'resolved',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('report_type')
                    ->options([
                        'reassignment_request' => 'Reassignment Request',
                        'wrong_assignment' => 'Wrong Assignment',
                        'bad_demo' => 'Bad Demo',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'resolved' => 'Resolved',
                    ]),
            ])
            ->actions([
                // Approve Reassignment
                Tables\Actions\Action::make('approve_reassignment')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DemoAssignmentReport $record) =>
                        $record->status === 'pending' &&
                        $record->report_type === 'reassignment_request' &&
                        $record->suggested_record_id
                    )
                    ->requiresConfirmation()
                    ->action(function (DemoAssignmentReport $record) {
                        // Update the demo's record assignment
                        $record->demo->update([
                            'record_id' => $record->suggested_record_id,
                            'status' => 'assigned',
                            'manually_assigned' => true,
                        ]);

                        $record->update([
                            'status' => 'approved',
                            'resolved_by_admin_id' => auth()->id(),
                            'resolved_at' => now(),
                        ]);

                        \Illuminate\Support\Facades\Notification::make()
                            ->title('Reassignment approved')
                            ->success()
                            ->send();
                    }),

                // Delete Bad Demo
                Tables\Actions\Action::make('delete_demo')
                    ->label('Delete Demo')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (DemoAssignmentReport $record) =>
                        $record->status === 'pending' &&
                        $record->report_type === 'bad_demo'
                    )
                    ->requiresConfirmation()
                    ->action(function (DemoAssignmentReport $record) {
                        $record->demo->delete();

                        $record->update([
                            'status' => 'resolved',
                            'resolved_by_admin_id' => auth()->id(),
                            'resolved_at' => now(),
                            'admin_notes' => 'Demo deleted',
                        ]);

                        \Illuminate\Support\Facades\Notification::make()
                            ->title('Demo deleted')
                            ->success()
                            ->send();
                    }),

                // Reject Report
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DemoAssignmentReport $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Reason for rejection'),
                    ])
                    ->action(function (DemoAssignmentReport $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'resolved_by_admin_id' => auth()->id(),
                            'resolved_at' => now(),
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_all')
                        ->label('Approve Selected Reassignments')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record->report_type === 'reassignment_request' && $record->suggested_record_id) {
                                    $record->demo->update([
                                        'record_id' => $record->suggested_record_id,
                                        'status' => 'assigned',
                                        'manually_assigned' => true,
                                    ]);

                                    $record->update([
                                        'status' => 'approved',
                                        'resolved_by_admin_id' => auth()->id(),
                                        'resolved_at' => now(),
                                    ]);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemoAssignmentReports::route('/'),
        ];
    }
}
```

### 7.4 Add User Restriction Actions to User Filament Resource

**File**: `app/Filament/Resources/UserResource.php`

Add to the `table()` method's actions:

```php
Tables\Actions\Action::make('restrict_uploads')
    ->label('Restrict Uploads')
    ->icon('heroicon-o-no-symbol')
    ->color('warning')
    ->visible(fn (User $record) => !$record->upload_restricted)
    ->requiresConfirmation()
    ->action(function (User $record) {
        $record->update(['upload_restricted' => true]);
    }),

Tables\Actions\Action::make('unrestrict_uploads')
    ->label('Allow Uploads')
    ->icon('heroicon-o-check')
    ->color('success')
    ->visible(fn (User $record) => $record->upload_restricted)
    ->action(function (User $record) {
        $record->update(['upload_restricted' => false]);
    }),

Tables\Actions\Action::make('restrict_assignments')
    ->label('Restrict Assignments')
    ->icon('heroicon-o-no-symbol')
    ->color('warning')
    ->visible(fn (User $record) => !$record->assignment_restricted)
    ->requiresConfirmation()
    ->action(function (User $record) {
        $record->update(['assignment_restricted' => true]);
    }),

Tables\Actions\Action::make('unrestrict_assignments')
    ->label('Allow Assignments')
    ->icon('heroicon-o-check')
    ->color('success')
    ->visible(fn (User $record) => $record->assignment_restricted)
    ->action(function (User $record) {
        $record->update(['assignment_restricted' => false]);
    }),

Tables\Actions\Action::make('bulk_delete_demos')
    ->label('Delete All User Demos')
    ->icon('heroicon-o-trash')
    ->color('danger')
    ->requiresConfirmation()
    ->modalHeading('Delete all demos uploaded by this user?')
    ->modalDescription('This action cannot be undone.')
    ->action(function (User $record) {
        $count = $record->uploadedDemos()->count();
        $record->uploadedDemos()->delete();

        \Illuminate\Support\Facades\Notification::make()
            ->title("Deleted {$count} demos")
            ->success()
            ->send();
    }),

Tables\Actions\Action::make('bulk_remove_assignments')
    ->label('Remove All User Assignments')
    ->icon('heroicon-o-arrow-path')
    ->color('warning')
    ->requiresConfirmation()
    ->modalHeading('Remove all manual assignments made by this user?')
    ->modalDescription('Demos will be set back to failed status.')
    ->action(function (User $record) {
        $demos = \App\Models\UploadedDemo::where('manually_assigned', true)
            ->whereHas('assignmentReports', function($q) use ($record) {
                $q->where('reported_by_user_id', $record->id)
                  ->where('status', 'approved');
            })
            ->get();

        foreach ($demos as $demo) {
            $demo->update([
                'record_id' => null,
                'status' => 'failed',
                'manually_assigned' => false,
            ]);
        }

        \Illuminate\Support\Facades\Notification::make()
            ->title("Removed {$demos->count()} assignments")
            ->success()
            ->send();
    }),
```

---

## **Phase 8: Retroactive Demo Matching**

### 8.1 Create RematchDemosByAlias Job

**File**: `app/Jobs/RematchDemosByAlias.php`

```php
<?php

namespace App\Jobs;

use App\Models\UserAlias;
use App\Models\UploadedDemo;
use App\Services\NameMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RematchDemosByAlias implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $alias;

    public function __construct(UserAlias $alias)
    {
        $this->alias = $alias;
    }

    public function handle()
    {
        Log::info('RematchDemosByAlias: Starting retroactive matching', [
            'alias_id' => $this->alias->id,
            'alias' => $this->alias->alias,
            'user_id' => $this->alias->user_id,
        ]);

        // Find all demos with confidence < 100 or status = failed
        $demos = UploadedDemo::where(function($q) {
                $q->where('name_confidence', '<', 100)
                  ->orWhere('status', 'failed')
                  ->orWhereNull('name_confidence');
            })
            ->whereNotNull('player_name')
            ->get();

        $rematched = 0;
        $improved = 0;

        foreach ($demos as $demo) {
            // Rematch using NameMatcher
            $nameMatch = NameMatcher::findBestMatch($demo->player_name, $demo->user_id);

            $oldConfidence = $demo->name_confidence ?? 0;

            // Update confidence if improved
            if ($nameMatch['confidence'] > $oldConfidence) {
                $demo->update([
                    'name_confidence' => $nameMatch['confidence'],
                    'suggested_user_id' => $nameMatch['user_id'],
                ]);

                $improved++;

                Log::info('RematchDemosByAlias: Improved confidence', [
                    'demo_id' => $demo->id,
                    'old_confidence' => $oldConfidence,
                    'new_confidence' => $nameMatch['confidence'],
                ]);
            }

            // If 100% match now, try to reassign
            if ($nameMatch['confidence'] === 100 && $demo->status !== 'assigned') {
                // TODO: Implement auto-reassignment logic here
                // This would require refactoring DemoProcessorService->autoAssignToRecord
                // to be publicly accessible and reusable
                $rematched++;
            }
        }

        Log::info('RematchDemosByAlias: Completed', [
            'alias_id' => $this->alias->id,
            'total_checked' => $demos->count(),
            'improved_confidence' => $improved,
            'rematched' => $rematched,
        ]);
    }
}
```

### 8.2 Create Artisan Command

**File**: `app/Console/Commands/RematchAllDemos.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use App\Services\NameMatcher;
use Illuminate\Console\Command;

class RematchAllDemos extends Command
{
    protected $signature = 'demos:rematch-all';
    protected $description = 'Rematch all unassigned demos against current user aliases';

    public function handle()
    {
        $this->info('Rematching all unassigned demos...');

        $demos = UploadedDemo::where(function($q) {
                $q->where('name_confidence', '<', 100)
                  ->orWhere('status', 'failed')
                  ->orWhereNull('name_confidence');
            })
            ->whereNotNull('player_name')
            ->get();

        $this->info("Found {$demos->count()} demos to rematch");

        $progressBar = $this->output->createProgressBar($demos->count());
        $progressBar->start();

        $improved = 0;

        foreach ($demos as $demo) {
            $nameMatch = NameMatcher::findBestMatch($demo->player_name, $demo->user_id);

            $oldConfidence = $demo->name_confidence ?? 0;

            if ($nameMatch['confidence'] > $oldConfidence) {
                $demo->update([
                    'name_confidence' => $nameMatch['confidence'],
                    'suggested_user_id' => $nameMatch['user_id'],
                ]);
                $improved++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Done! Improved confidence for {$improved} demos.");
    }
}
```

---

## **Phase 9: UI Changes - Map Details "Demos Top"**

### 9.1 Update MapDetails.vue

**File**: `resources/js/Components/MapDetails.vue`

Find the "Offline Demos" toggle and change label to **"Demos Top"**:

```vue
<!-- Change from "Offline Demos" to "Demos Top" -->
<button
    @click="toggleOfflineRecords"
    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
>
    <span v-if="!showingOfflineRecords">Show Demos Top</span>
    <span v-else>Hide Demos Top</span>
</button>
```

### 9.2 Update Backend - Map Controller

Ensure the backend returns combined data:
- All offline_records
- Online demos with name_confidence < 100

---

## **Phase 10: UI Changes - Demos Section Filters**

### 10.1 Update DemosController

**File**: `app/Http/Controllers/DemosController.php`

Add filter parameters to the `index()` method:

```php
public function index(Request $request)
{
    // ... existing code ...

    // NEW: Confidence filter
    $confidenceFilter = $request->input('confidence');

    // NEW: Other user matches filter
    $showOtherUserMatches = $request->input('other_user_matches');

    // NEW: Uploaded by filter
    $uploadedBy = $request->input('uploaded_by');

    // Get user's own uploads if authenticated
    $userDemos = null;
    $demoCounts = null;
    if (Auth::check()) {
        if ($isAdmin) {
            $query = UploadedDemo::with(['record.user', 'user', 'offlineRecord', 'suggestedUser']);

            // Apply confidence filters
            if ($confidenceFilter) {
                switch ($confidenceFilter) {
                    case '90-99':
                        $query->whereBetween('name_confidence', [90, 99]);
                        break;
                    case '80-89':
                        $query->whereBetween('name_confidence', [80, 89]);
                        break;
                    case '70-79':
                        $query->whereBetween('name_confidence', [70, 79]);
                        break;
                    case '60-69':
                        $query->whereBetween('name_confidence', [60, 69]);
                        break;
                    case '50-59':
                        $query->whereBetween('name_confidence', [50, 59]);
                        break;
                    case 'below-50':
                        $query->where('name_confidence', '<', 50);
                        break;
                }
            }

            // Filter for demos matched to other users
            if ($showOtherUserMatches) {
                $query->where('name_confidence', 100)
                      ->whereNotNull('suggested_user_id')
                      ->where('suggested_user_id', '!=', Auth::id());
            }

            // Filter by uploader
            if ($uploadedBy) {
                $uploaderUser = User::where('name', $uploadedBy)->first();
                if ($uploaderUser) {
                    $query->where('user_id', $uploaderUser->id);
                }
            }

            // Apply sorting
            if ($sortBy === 'status') {
                $query->orderByRaw("FIELD(status, 'assigned', 'processed', 'processing', 'pending', 'uploaded', 'failed')");
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            $userDemos = $query->paginate(20, ['*'], 'userPage');

            // ... rest of code
        }
    }

    return Inertia::render('Demos/Index', [
        'userDemos' => $userDemos,
        'publicDemos' => $publicDemos,
        'demoCounts' => $demoCounts,
        'sortBy' => $sortBy,
        'sortOrder' => $sortOrder,
        'confidenceFilter' => $confidenceFilter,
        'showOtherUserMatches' => $showOtherUserMatches,
        'uploadedBy' => $uploadedBy,
        'downloadLimitInfo' => [
            'used' => $downloadsToday,
            'limit' => $maxDownloads,
            'remaining' => $remainingDownloads,
            'isGuest' => !$currentUser,
        ],
    ]);
}
```

### 10.2 Update Demos/Index.vue

**File**: `resources/js/Pages/Demos/Index.vue`

Add new filter buttons and display confidence badges:

```vue
<template>
    <!-- ... existing content ... -->

    <!-- NEW: Additional Filters Section -->
    <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <h3 class="text-lg font-bold text-white mb-4">Advanced Filters</h3>

        <div class="flex flex-wrap gap-3">
            <!-- Uploaded By Filter -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-semibold text-gray-300 mb-2">Uploaded By</label>
                <input
                    v-model="uploadedByFilter"
                    @keyup.enter="applyFilters"
                    type="text"
                    placeholder="Username..."
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none"
                />
            </div>

            <!-- 100% Name Match (Other User) -->
            <div class="flex items-end">
                <button
                    @click="toggleOtherUserMatches"
                    :class="[
                        'px-4 py-2 rounded-lg transition-colors font-semibold',
                        showOtherUserMatches
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                    ]"
                >
                    100% Match (Other User)
                </button>
            </div>

            <!-- Confidence Filter Dropdown -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-semibold text-gray-300 mb-2">Name Confidence</label>
                <select
                    v-model="confidenceFilterValue"
                    @change="applyFilters"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none"
                >
                    <option value="">All Confidence Levels</option>
                    <option value="90-99">90-99%</option>
                    <option value="80-89">80-89%</option>
                    <option value="70-79">70-79%</option>
                    <option value="60-69">60-69%</option>
                    <option value="50-59">50-59%</option>
                    <option value="below-50">Below 50%</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Demo Table with Confidence Badges -->
    <tbody class="divide-y divide-gray-600">
        <tr v-for="demo in filteredDemos" :key="demo.id" class="hover:bg-gray-700/30 transition-colors duration-200 group">
            <td class="px-6 py-4 text-sm">
                <div class="flex items-center space-x-3">
                    <svg class="w-5 h-5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <div>
                        <span class="text-gray-200 font-medium">{{ demo.processed_filename || demo.original_filename }}</span>

                        <!-- NEW: Confidence Badge -->
                        <div v-if="demo.name_confidence !== null" class="mt-1 flex items-center gap-2">
                            <span
                                :class="[
                                    'text-xs px-2 py-0.5 rounded',
                                    demo.name_confidence === 100 ? 'bg-green-500/20 text-green-400' :
                                    demo.name_confidence >= 90 ? 'bg-blue-500/20 text-blue-400' :
                                    demo.name_confidence >= 70 ? 'bg-yellow-500/20 text-yellow-400' :
                                    'bg-red-500/20 text-red-400'
                                ]"
                            >
                                {{ demo.name_confidence }}% confidence
                            </span>

                            <span v-if="demo.suggested_user_id" class="text-xs text-gray-400">
                                → {{ demo.suggestedUser?.name }}
                            </span>

                            <span v-if="demo.manually_assigned" class="text-xs bg-purple-500/20 text-purple-400 px-2 py-0.5 rounded">
                                Manually Assigned
                            </span>
                        </div>
                    </div>
                </div>
            </td>

            <!-- ... other columns ... -->

            <!-- NEW: Report Button (appears on hover) -->
            <td class="px-6 py-4 text-sm">
                <div class="flex items-center gap-2">
                    <!-- Existing action buttons -->

                    <!-- Report Button -->
                    <button
                        v-if="$page.props.auth.user && canReportDemos"
                        @click="openReportModal(demo)"
                        class="opacity-0 group-hover:opacity-100 transition-opacity text-yellow-400 hover:text-yellow-300"
                        title="Report demo"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    </tbody>

    <!-- Report Modal -->
    <DemoReportModal
        :show="showReportModal"
        :demo="reportingDemo"
        @close="closeReportModal"
    />
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import DemoReportModal from '@/Components/DemoReportModal.vue';

const props = defineProps({
    userDemos: Object,
    publicDemos: Object,
    demoCounts: Object,
    sortBy: String,
    sortOrder: String,
    confidenceFilter: String,
    showOtherUserMatches: Boolean,
    uploadedBy: String,
    downloadLimitInfo: Object,
});

const uploadedByFilter = ref(props.uploadedBy || '');
const confidenceFilterValue = ref(props.confidenceFilter || '');
const showOtherUserMatches = ref(props.showOtherUserMatches || false);

const showReportModal = ref(false);
const reportingDemo = ref(null);

const canReportDemos = computed(() => {
    // User needs 30 records to report
    return props.$page?.props?.auth?.user?.records_count >= 30;
});

const applyFilters = () => {
    router.get(route('demos.index'), {
        uploaded_by: uploadedByFilter.value || undefined,
        confidence: confidenceFilterValue.value || undefined,
        other_user_matches: showOtherUserMatches.value || undefined,
        sort: props.sortBy,
        order: props.sortOrder,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const toggleOtherUserMatches = () => {
    showOtherUserMatches.value = !showOtherUserMatches.value;
    applyFilters();
};

const openReportModal = (demo) => {
    reportingDemo.value = demo;
    showReportModal.value = true;
};

const closeReportModal = () => {
    showReportModal.value = false;
    reportingDemo.value = null;
};
</script>
```

---

## **Phase 11: Manual Assignment Feature**

Already covered in Phase 6 (Demo Reporting System) - the "Reassignment Request" report type serves as the manual assignment feature.

---

## **Phase 12: Upload & Assignment Restrictions**

### 12.1 Update Demo Upload Validation

**File**: `app/Http/Controllers/DemosController.php`

In the `upload()` method, add at the beginning:

```php
public function upload(Request $request)
{
    $currentUser = Auth::user();

    // NEW: Check if user is logged in
    if (!$currentUser) {
        return response()->json([
            'success' => false,
            'message' => 'You must be logged in to upload demos.',
        ], 403);
    }

    // NEW: Check if user meets upload requirements
    if (!$currentUser->canUploadDemos()) {
        $recordsCount = $currentUser->records()->count();

        if ($currentUser->upload_restricted) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been restricted from uploading demos. Please contact an administrator.',
            ], 403);
        }

        return response()->json([
            'success' => false,
            'message' => "You need at least 30 records to upload demos. You currently have {$recordsCount} record(s).",
        ], 403);
    }

    // ... rest of upload logic
}
```

### 12.2 Update Frontend Upload UI

**File**: `resources/js/Pages/Demos/Index.vue`

Disable upload UI if user doesn't meet requirements:

```vue
<template>
    <!-- Upload Section -->
    <div class="bg-gray-800/50 rounded-xl p-6 border border-gray-700">
        <h2 class="text-xl font-bold text-white mb-4">Upload Demos</h2>

        <!-- NEW: Requirements Check -->
        <div v-if="!$page.props.auth.user" class="bg-yellow-500/10 border border-yellow-500/50 rounded-lg p-4 mb-4">
            <p class="text-yellow-400">You must be logged in to upload demos.</p>
        </div>

        <div v-else-if="!canUpload" class="bg-red-500/10 border border-red-500/50 rounded-lg p-4 mb-4">
            <p class="text-red-400">
                {{ uploadRestrictionMessage }}
            </p>
        </div>

        <!-- Upload UI (disabled if can't upload) -->
        <div :class="{ 'opacity-50 pointer-events-none': !canUpload }">
            <!-- ... existing upload UI ... -->
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const canUpload = computed(() => {
    const user = props.$page?.props?.auth?.user;
    if (!user) return false;
    if (user.upload_restricted) return false;
    return (user.records_count || 0) >= 30;
});

const uploadRestrictionMessage = computed(() => {
    const user = props.$page?.props?.auth?.user;
    if (!user) return '';

    if (user.upload_restricted) {
        return 'Your account has been restricted from uploading demos. Please contact an administrator.';
    }

    const recordsCount = user.records_count || 0;
    const needed = 30 - recordsCount;
    return `You need at least 30 records to upload demos. You currently have ${recordsCount} record(s). ${needed} more needed.`;
});
</script>
```

### 12.3 Track Download Count

**File**: `app/Http/Controllers/DemosController.php`

In the `download()` method, increment download counter:

```php
public function download(UploadedDemo $demo)
{
    // ... existing download logic ...

    // NEW: Increment download counter
    $demo->incrementDownloads();

    // ... return file response
}
```

---

## **Phase 13: Testing Checklist**

### Database & Models:
- [ ] Run migrations successfully
- [ ] UserAlias model relationships work
- [ ] AliasReport model relationships work
- [ ] DemoAssignmentReport model relationships work
- [ ] User restriction flags work correctly
- [ ] UploadedDemo confidence fields store correctly

### Name Matching:
- [ ] Quake color code stripping works (^0-^9, ^[, ^])
- [ ] Exact match returns 100% confidence
- [ ] Fuzzy matching calculates correct percentages
- [ ] Two-pass matching (uploader priority, then global)
- [ ] Matching against user nicknames works
- [ ] Matching against user aliases works
- [ ] NameMatcher logs helpful debugging info

### Demo Processing:
- [ ] 100% name match → auto-assign to online record
- [ ] <100% name match → create offline_record
- [ ] Offline demo → create offline_record
- [ ] Guest upload with 100% global match works
- [ ] Logged-in user uploads someone else's demo (100% match)
- [ ] Confidence scores stored correctly
- [ ] suggested_user_id stored correctly

### Alias System:
- [ ] User can create alias (up to 10)
- [ ] Alias uniqueness validation works
- [ ] Restricted user creates pending alias
- [ ] Delete alias works
- [ ] Alias appears on profile page
- [ ] Report alias works (requires 30 records)
- [ ] Can't report same alias twice

### Upload Restrictions:
- [ ] Guests cannot upload
- [ ] Users with <30 records cannot upload
- [ ] Upload-restricted users cannot upload
- [ ] Error messages display correctly
- [ ] Upload UI disabled when requirements not met

### Demo Reporting:
- [ ] Report button appears on hover in demos section
- [ ] Report button appears on map details
- [ ] Requires 30 records to report
- [ ] Reassignment request requires record selection
- [ ] Can't report same demo within 7 days
- [ ] Report modal displays correctly
- [ ] Predefined reasons populate correctly
- [ ] Report submission works

### Admin Panel:
- [ ] UserAlias resource displays correctly
- [ ] Can approve pending aliases
- [ ] Can delete aliases
- [ ] AliasReport resource works
- [ ] Can resolve/dismiss alias reports
- [ ] DemoAssignmentReport resource works
- [ ] Can approve reassignment requests
- [ ] Can delete bad demos
- [ ] Can reject reports with notes
- [ ] User restriction actions work
- [ ] Bulk delete user demos works
- [ ] Bulk remove user assignments works

### Retroactive Matching:
- [ ] RematchDemosByAlias job runs when alias added
- [ ] demos:rematch-all command works
- [ ] Confidence scores improve after adding alias
- [ ] Demos reassigned when 100% match found

### UI Changes:
- [ ] "Demos Top" label displays on map details
- [ ] Confidence filters work in demos section
- [ ] "Uploaded By" filter works
- [ ] "100% Match (Other User)" filter works
- [ ] Confidence badges display correctly
- [ ] Color coding for confidence levels works
- [ ] "Manually Assigned" badge shows
- [ ] Top 5 downloaded demos show on profile
- [ ] Aliases display on profile page
- [ ] Report button shows for other users' aliases

### Download Tracking:
- [ ] Download count increments correctly
- [ ] Top downloaded demos query works
- [ ] Profile stats display correctly

---

## **Implementation Order Summary**

### Week 1: Foundation
1. ✅ **Day 1-2**: Phase 1 - Database migrations and models
2. ✅ **Day 3**: Phase 2 - NameMatcher service
3. ✅ **Day 4-5**: Phase 3 - Update DemoProcessorService

### Week 2: User Features
4. ✅ **Day 1-2**: Phase 4 - Profile settings alias management
5. ✅ **Day 3**: Phase 5 - Profile page stats & aliases display
6. ✅ **Day 4-5**: Phase 6 - Demo reporting system

### Week 3: Admin Tools
7. ✅ **Day 1-3**: Phase 7 - Filament admin panel resources
8. ✅ **Day 4**: Phase 8 - Retroactive matching system

### Week 4: UI Polish
9. ✅ **Day 1**: Phase 9 - Map details "Demos Top" rename
10. ✅ **Day 2-3**: Phase 10 - Demos section enhanced filters
11. ✅ **Day 4**: Phase 12 - Upload & assignment restrictions

### Week 5: Testing
12. ✅ **Day 1-5**: Phase 13 - Comprehensive testing and bug fixes

---

## **Files Summary**

### New Files Created:
1. `database/migrations/XXXX_create_user_aliases_table.php`
2. `database/migrations/XXXX_create_alias_reports_table.php`
3. `database/migrations/XXXX_create_demo_assignment_reports_table.php`
4. `database/migrations/XXXX_add_restrictions_to_users_table.php`
5. `database/migrations/XXXX_add_name_matching_to_uploaded_demos_table.php`
6. `app/Models/UserAlias.php`
7. `app/Models/AliasReport.php`
8. `app/Models/DemoAssignmentReport.php`
9. `app/Services/NameMatcher.php`
10. `app/Http/Controllers/AliasController.php`
11. `app/Http/Controllers/AliasReportController.php`
12. `app/Http/Controllers/DemoReportController.php`
13. `app/Jobs/RematchDemosByAlias.php`
14. `app/Console/Commands/RematchAllDemos.php`
15. `app/Filament/Resources/UserAliasResource.php`
16. `app/Filament/Resources/AliasReportResource.php`
17. `app/Filament/Resources/DemoAssignmentReportResource.php`
18. `resources/js/Components/DemoReportModal.vue`
19. `resources/js/Pages/Profile/Settings.vue` (if doesn't exist)

### Modified Files:
1. `app/Models/User.php` - Add aliases relationship, helper methods
2. `app/Models/UploadedDemo.php` - Add relationships, download tracking
3. `app/Services/DemoProcessorService.php` - Update autoAssignToRecord method
4. `app/Http/Controllers/DemosController.php` - Add filters, upload restrictions
5. `app/Http/Controllers/ProfileController.php` - Load aliases and demo stats
6. `app/Filament/Resources/UserResource.php` - Add restriction actions
7. `resources/js/Components/MapDetails.vue` - Rename to "Demos Top"
8. `resources/js/Pages/Demos/Index.vue` - Add filters, report modal, restrictions
9. `resources/js/Pages/Profile.vue` - Display aliases and top demos
10. `routes/web.php` - Add new routes

---

## **Configuration Requirements**

### Environment Variables:
None required beyond existing Laravel/Backblaze setup

### Queue Configuration:
Ensure queue workers are running for background jobs:
```bash
php artisan queue:work
```

### Scheduled Tasks:
Optionally add to scheduler for periodic rematching:
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Rematch demos weekly
    $schedule->command('demos:rematch-all')->weekly();
}
```

---

## **End of Implementation Plan**

This comprehensive plan covers all aspects of the player alias system, intelligent demo matching, reporting system, and admin management tools. Follow the implementation order for best results, and use the testing checklist to ensure everything works correctly before deploying to production.

**Total estimated implementation time**: 4-5 weeks

**Priority features for MVP**:
1. Name matching system (Phase 2-3)
2. Basic alias management (Phase 4-5)
3. Upload restrictions (Phase 12)
4. Demo reporting (Phase 6)
5. Admin panel (Phase 7)

**Nice-to-have features** (can be added later):
- Retroactive matching automation
- Advanced confidence filters
- Download tracking stats
- Bulk admin actions
