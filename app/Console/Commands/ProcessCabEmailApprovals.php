<?php

namespace App\Console\Commands;

use App\Services\EwsCabMailReader;
use Exception;
use Illuminate\Console\Command;
use Log;

class ProcessCabEmailApprovals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cab:process-email-approvals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process cab email approvals from the inbox';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $mailReader = new EwsCabMailReader();
            $result = $mailReader->handleApprovals(20);
            $this->info('Checked cab inbox and processed approvals.');

            return 0;
        } catch (Exception $e) {
            $this->error('Error processing cab email approvals: ' . $e->getMessage());
            Log::error('CAB Email processing error: ' . $e->getMessage());

            return 1;
        }
    }
}
