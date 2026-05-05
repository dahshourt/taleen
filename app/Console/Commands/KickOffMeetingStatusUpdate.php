<?php

namespace App\Console\Commands;

use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use Carbon\Carbon;
use Exception;
use App\Models\User;
use App\Models\ChangeRequestCustomField;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KickOffMeetingStatusUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cr:update-kickoff-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update change request status when kick off meeting date has started (today or past)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $this->info('Starting kick off meeting status update process...');

            $adminUser = User::where('email', 'admin@te.eg')->first();
            // Get today's date
            $today = Carbon::today();
            $todayFormatted = $today->format('Y-m-d');

            $this->info("Checking for kick off meetings that should start on or before: {$todayFormatted}");

            // Query the change_request_custom_fields table
            // Get all records with kick_off_meeting_date field
            $changeRequests = ChangeRequestCustomField::where('custom_field_name', 'kick_off_meeting_date')
                ->whereNotNull('custom_field_value')
                ->where('custom_field_value', '!=', '')
                ->whereHas('change_request.currentRequestStatuses', function ($q) {
                    $q->where('new_status_id', '103')
                        ->active();
                })
                ->get()
                ->filter(function ($cr) {
                    try {
                        // Parse the date from database and compare
                        $kickOffDate = Carbon::parse($cr->custom_field_value);

                        // Check if the kick off date is already passed
                        $shouldProcess = $kickOffDate->isPast();

                        if ($shouldProcess) {
                            $this->line("Found CR ID: {$cr->cr_id} with kick off date: {$cr->custom_field_value}");
                        }

                        return $shouldProcess;

                    } catch (Exception $e) {
                        $this->warn("Skipping CR ID: {$cr->cr_id} - Invalid date format: {$cr->custom_field_value}");

                        return false;
                    }
                });

            if ($changeRequests->isEmpty()) {
                $this->info('No change requests found with kick off meeting date that has started (today or past).');

                return Command::SUCCESS;
            }

            $this->info("Found {$changeRequests->count()} change request(s) to process.");

            $repo = new ChangeRequestRepository();
            $updatedCount = 0;
            $errorCount = 0;

            // You may want to get this from config or a specific user
            // Consider using: config('app.system_user_id', 1) or Auth::id()
            foreach ($changeRequests as $cr) {
                $new_status_id = $cr->change_request->getSetStatus()->where('workflow_type', 0)->first()->id;
                $user_id = $adminUser->id;
                try {
                    $crId = $cr->cr_id;


                    $requestData = new Request([
                        'old_status_id' => '103',
                        'new_status_id' => $new_status_id,
                        'user_id' => $user_id,
                    ]);

                    $result = $repo->update($crId, $requestData);

                    if ($result) {
                        $this->info("✓ Successfully updated CR ID: {$crId} from status 103 to 104");
                        $updatedCount++;
                    } else {
                        $this->error("✗ Failed to update CR ID: {$crId} - Repository returned false");
                        $errorCount++;
                    }

                } catch (Exception $e) {
                    $this->error("✗ Failed to update CR ID: {$cr->cr_id}. Error: " . $e->getMessage());
                    $errorCount++;
                }
            }

            // Summary
            $this->info('=== Process Completed ===');
            $this->info("Successfully updated: {$updatedCount} change requests");

            if ($errorCount > 0) {
                $this->warn("Failed to update: {$errorCount} change requests");
            }

            $this->info('Total processed: ' . ($updatedCount + $errorCount));

        } catch (Exception $e) {
            $this->error('Command failed with critical error: ' . $e);
            Log::error('Command failed with critical error: ' . $e);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
