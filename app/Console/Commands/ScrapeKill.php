<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScrapeKill extends Command
{
    protected $signature = 'scrape:kill';
    protected $description = 'Kill all running scraper processes';

    public function handle()
    {
        $this->info('Searching for scraper processes...');

        exec('ps aux | grep "scrape:records" | grep -v grep', $output);

        if (empty($output)) {
            $this->info('No scraper processes found.');
            return 0;
        }

        $this->warn('Found ' . count($output) . ' scraper process(es):');

        $pids = [];
        foreach ($output as $line) {
            $this->line('  ' . $line);
            // Extract PID from ps output
            if (preg_match('/^\S+\s+(\d+)/', $line, $matches)) {
                $pids[] = $matches[1];
            }
        }

        $this->newLine();
        if ($this->confirm('Kill all these processes?', true)) {
            foreach ($pids as $pid) {
                exec("kill -9 {$pid}");
            }
            $this->info('✓ Killed ' . count($pids) . ' scraper process(es).');
        } else {
            $this->info('Cancelled.');
        }

        return 0;
    }
}
