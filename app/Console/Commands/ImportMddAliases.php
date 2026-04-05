<?php

namespace App\Console\Commands;

use App\Models\MddProfile;
use App\Models\UserAlias;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMddAliases extends Command
{
    protected $signature = 'import:mdd-aliases {path : Path to mdd_aliases.db sqlite file} {--dry-run : Show what would be imported without writing}';
    protected $description = 'Import player aliases from MDD scrape sqlite database';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        $sqlite = new \SQLite3($path, SQLITE3_OPEN_READONLY);

        // Count totals
        $playerCount = $sqlite->querySingle('SELECT COUNT(*) FROM players');
        $aliasCount = $sqlite->querySingle('SELECT COUNT(*) FROM aliases');
        $this->info("Source: {$playerCount} players, {$aliasCount} aliases");

        // Load all mdd_profiles ids + user_id mapping
        $mddProfiles = MddProfile::select('id', 'user_id')->get();
        $existingMddIds = $mddProfiles->pluck('id')->flip();
        $mddUserMap = $mddProfiles->whereNotNull('user_id')->pluck('user_id', 'id');
        $this->info("Existing mdd_profiles: {$existingMddIds->count()} ({$mddUserMap->count()} with user accounts)");

        // Load existing mdd aliases to avoid duplicates (keyed by mdd_id:alias_colored)
        $existingAliases = UserAlias::where('source', 'mdd_import')
            ->select('mdd_id', 'alias_colored')
            ->get()
            ->map(fn ($a) => $a->mdd_id . ':' . $a->alias_colored)
            ->flip();

        // Delete manual aliases that will be replaced by MDD import
        if (!$this->option('dry-run')) {
            $manualConverted = 0;
            $manualAliases = UserAlias::where('source', 'manual')->whereNotNull('user_id')->get();
            foreach ($manualAliases as $manual) {
                $userId = $manual->user_id;
                $mddId = \App\Models\User::where('id', $userId)->value('mdd_id');
                if (!$mddId) continue;

                // Check if this alias exists in the import sqlite
                $check = $sqlite->querySingle("SELECT COUNT(*) FROM aliases WHERE player_id = {$mddId} AND alias_clean = '" . $sqlite->escapeString($manual->alias) . "'");
                if ($check > 0) {
                    $manual->delete();
                    $manualConverted++;
                }
            }
            if ($manualConverted > 0) {
                $this->info("Replaced {$manualConverted} manual aliases with MDD imports");
            }
        }

        $imported = 0;
        $skippedNoProfile = 0;
        $skippedDuplicate = 0;
        $missingMddIds = [];

        $results = $sqlite->query('
            SELECT a.player_id as mdd_id, a.alias_clean, a.alias_colored, a.usage_count
            FROM aliases a
            ORDER BY a.player_id, a.usage_count DESC
        ');

        $batch = [];

        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $mddId = (int) $row['mdd_id'];
            $aliasClean = trim($row['alias_clean']);
            $aliasColored = trim($row['alias_colored']);
            $usageCount = (int) $row['usage_count'];

            if ($aliasClean === '') continue;

            // Check mdd_profile exists
            if (!$existingMddIds->has($mddId)) {
                $skippedNoProfile++;
                $missingMddIds[$mddId] = true;
                continue;
            }

            // Check duplicate
            $dupeKey = $mddId . ':' . $aliasColored;
            if ($existingAliases->has($dupeKey)) {
                $skippedDuplicate++;
                continue;
            }

            $batch[] = [
                'user_id' => $mddUserMap->get($mddId),
                'mdd_id' => $mddId,
                'alias' => $aliasClean,
                'alias_colored' => $aliasColored,
                'usage_count' => $usageCount,
                'source' => 'mdd_import',
                'is_approved' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $imported++;

            // Insert in batches of 500
            if (count($batch) >= 500) {
                if (!$this->option('dry-run')) {
                    UserAlias::insert($batch);
                }
                $this->output->write("\r  Imported: {$imported}");
                $batch = [];
            }
        }

        // Insert remaining
        if (!empty($batch) && !$this->option('dry-run')) {
            UserAlias::insert($batch);
        }

        $sqlite->close();

        $this->newLine();
        $this->info("Imported: {$imported}");
        $this->info("Skipped (no mdd_profile): {$skippedNoProfile}");
        $this->info("Skipped (duplicate): {$skippedDuplicate}");

        if (!empty($missingMddIds)) {
            $this->warn("Missing mdd_ids: " . implode(', ', array_keys($missingMddIds)));
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN - nothing was written to database");
        }

        return 0;
    }
}
