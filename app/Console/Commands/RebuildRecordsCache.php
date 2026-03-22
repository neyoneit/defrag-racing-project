<?php

namespace App\Console\Commands;

use App\Http\Controllers\RecordsController;
use Illuminate\Console\Command;

class RebuildRecordsCache extends Command
{
    protected $signature = 'records:rebuild-cache';

    protected $description = 'Fully rebuild the records page cache (counts + page 1 for all modes/physics)';

    public function handle(): void
    {
        $this->info('Rebuilding records cache...');

        RecordsController::rebuildCache();

        $this->info('Records cache rebuilt successfully.');
    }
}
