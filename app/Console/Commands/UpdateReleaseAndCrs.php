<?php

namespace App\Console\Commands;

use App\Factories\Releases\ReleaseFactory;
use Illuminate\Console\Command;

class UpdateReleaseAndCrs extends Command
{
    protected $releaseController;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateReleaseAndCrs:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(ReleaseFactory $releaseFactory)
    {
        parent::__construct();

        // Manually instantiate the controller with its dependency
        $this->releaseController = $releaseFactory::index();
    }

    /**
     * Create a new command instance.
     *
     * @return void
     */
    /*public function __construct()
    {
        parent::__construct();
    }*/

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->releaseController->update_release_its_crs();
    }
}
