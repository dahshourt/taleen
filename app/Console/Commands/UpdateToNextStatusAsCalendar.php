<?php

namespace App\Console\Commands;

use App\Factories\ChangeRequest\ChangeRequestFactory;
use Illuminate\Console\Command;

class UpdateToNextStatusAsCalendar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CalendarUpdateStatus:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the  promo CR to next status as per Calendar';

    private $changerequest;

    public function __construct(ChangeRequestFactory $changerequest)
    {
        parent::__construct();
        $this->changerequest = $changerequest::index();
    }

    /**
     * Create a new command instance.
     *
     * @return void
     */

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->changerequest->update_to_next_status_calendar();
    }
}
