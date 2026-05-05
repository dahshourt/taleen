<?php

namespace App\Console\Commands;

use App\Services\EwsMailReader;
use Exception;
use Illuminate\Console\Command;
use Log;

class ProcessEmailApprovals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-approvals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process email approvals from the inbox';

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
            $mailReader = new EwsMailReader();
            $result = $mailReader->handleApprovals(20);
            $this->info('Checked inbox and processed approvals.');

            return 0;
        } catch (Exception $e) {
            $this->error('Error processing email approvals: ' . $e->getMessage());
            Log::error('Email processing error: ' . $e->getMessage());

            return 1;
        }
    }
}
