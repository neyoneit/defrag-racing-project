<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Jobs\CalculateRatings as CalculateRatingsJob;

use Illuminate\Support\Facades\Log;

/**
 * @deprecated Use `ratings:calculate` (Rust) instead. Kept for reference only.
 */
class CalculateRatings extends Command
{
    protected $signature = 'run:calculate-ratings';
    protected $description = '[DEPRECATED] Calculate players ratings - use ratings:calculate instead';

    public function handle() {
        $this->error('This command is deprecated. Use `ratings:calculate --physics=vq3` and `ratings:calculate --physics=cpm` instead.');
        return 1;
    }
}
