<?php

namespace App\Console;

use App\Console\Commands\CleanupLogsCommand;
use App\Console\Commands\DirectorsApprovalsCommand;
use App\Console\Commands\OperationalDMAndCapacityApprovalCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\UpdateReleaseAndCrs::class,
        \App\Console\Commands\UpdateToNextStatusAsCalendar::class, // Fixed class name
        \App\Console\Commands\KickOffMeetingStatusUpdate::class,
        \App\Console\Commands\Reject_business_approvals::class,
        //  \App\Console\Commands\EwsListenerCommand::class,

        DirectorsApprovalsCommand::class,
        OperationalDMAndCapacityApprovalCommand::class,
        CleanupLogsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('CalendarUpdateStatus:run')->daily();
        $schedule->command('email:process-approvals')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
        $schedule->command('cab:process-email-approvals')->everyFiveMinutes()->withoutOverlapping()->runInBackground();
        $schedule->command('cab:approve-users')->daily();
        $schedule->command('cr:update-kickoff-status')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
        $schedule->command('cron:escalation')->everyFiveMinutes();
        $schedule->command('auto:reject-cr')->dailyAt('00:00');
        $schedule->command('cr:send-hold-reminders')->dailyAt('08:00')->withoutOverlapping();

        $schedule->command('directors:approvals')
            ->withoutOverlapping()
            ->runInBackground()
            ->everyMinute()
            ->days([0, 1, 2, 3, 4]) // Sunday (0) to Thursday (4)
            ->between('08:00', '16:00');

        $schedule->command('division-managers:approvals')
            ->withoutOverlapping()
            ->runInBackground()
            ->everyMinute()
            ->days([0, 1, 2, 3, 4]) // Sunday (0) to Thursday (4)
            ->between('08:00', '16:00');

        $schedule->command('logs:cleanup')->dailyAt('02:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
