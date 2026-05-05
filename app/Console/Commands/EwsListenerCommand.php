<?php

namespace App\Console\Commands;

use App\Services\EwsStreamingService;
use Illuminate\Console\Command;

class EwsListenerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ews:listen {--stop : Stop the listener}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start/Stop EWS email listener for CR approvals';

    protected $streamingService;

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
        if ($this->option('stop')) {
            $this->stopListener();

            return 0;
        }

        $this->startListener();

        return 0;
    }

    public function handleShutdown()
    {
        $this->info('Received shutdown signal, stopping EWS listener...');

        if ($this->streamingService) {
            $this->streamingService->stopListening();
        }

        exit(0);
    }

    protected function startListener()
    {
        $this->info('Starting EWS email listener...');

        $this->streamingService = new EwsStreamingService();

        // Only set up signal handling if PCNTL is available (not available on Windows)
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            $this->info('Signal handlers registered.');
        } else {
            $this->warn('PCNTL extension not available. Signal handling for graceful shutdown is disabled.');
            $this->warn('Press Ctrl+C to stop the listener (may take a moment to exit).');
        }

        $this->streamingService->startListening();
    }

    protected function stopListener()
    {
        $this->info('Stopping EWS email listener...');

        // You might want to implement a PID file system here
        // to properly stop running instances

        $this->info('EWS listener stop signal sent.');
    }
}
