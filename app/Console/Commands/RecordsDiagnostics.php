<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Record;

class RecordsDiagnostics extends Command
{
    protected $signature = 'records:diagnostics';

    protected $description = 'Compare api_records_log vs records table to find missing records';

    public function handle()
    {
        $apiLogCount = DB::table('api_records_log')->count();

        if ($apiLogCount === 0) {
            $this->warn('No records in api_records_log yet. Let the API scraper run for a while first.');
            return 0;
        }

        $this->info("API log contains {$apiLogCount} unique records.");
        $this->info("Records table contains " . Record::count() . " records.");
        $this->newLine();

        // Find records that API sent but we don't have in DB
        $missingFromDb = DB::table('api_records_log as a')
            ->leftJoin('records as r', function ($join) {
                $join->on('a.mdd_id', '=', 'r.mdd_id')
                    ->on('a.mapname', '=', 'r.mapname')
                    ->on('a.physics', '=', 'r.physics')
                    ->on('a.mode', '=', 'r.mode');
            })
            ->whereNull('r.id')
            ->select('a.*')
            ->get();

        if ($missingFromDb->isEmpty()) {
            $this->info('All API records exist in the database. No missing records detected.');
            $this->info('If the total count difference with q3df.org is still growing, the API is not sending some records.');
        } else {
            $this->error("Found {$missingFromDb->count()} records that API sent but are MISSING from database:");
            $this->newLine();

            foreach ($missingFromDb as $record) {
                $this->line("  [{$record->name}] ({$record->time}) ({$record->mapname}) ({$record->physics}-{$record->mode}) date: {$record->date_set} | first seen: {$record->first_seen_at}");
            }

            $this->newLine();
            $this->error('BUG CONFIRMED: Our code is dropping records that the API sends us.');
        }

        return 0;
    }
}
