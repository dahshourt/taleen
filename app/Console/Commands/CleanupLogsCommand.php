<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupLogsCommand extends Command
{
    protected $signature = 'logs:cleanup';

    protected $description = 'Clean Up old logs';

    public function handle(): void
    {
        $days = 30;
        $deleted = 0;

        do {
            $count = DB::table('log_viewers')
                ->where('created_at', '<', now()->subDays($days))
                ->limit(1000)
                ->delete();

            $deleted += $count;

        } while ($count > 0);

        $this->info("Deleted $deleted old logs.");
        Log::info("Deleted $deleted old logs.");
    }
}
