<?php

namespace App\Console\Commands;

use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Models\Configuration;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Reject_business_approvals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan auto:Reject-cr
     */
    protected $signature = 'auto:reject-cr';

    /**
     * The console command description.
     */
    protected $description = 'Automatically Reject CRs greater than 7 days with new_status_id = 22 and active = 1';

    /**
     * Execute the console command.
     */
    public function handle(ChangeRequestRepository $repo)
    {
        Log::info('=== Auto Reject CR job started ===');

        $adminUser = User::where('email', 'admin@te.eg')->first();

        if (! $adminUser) {
            Log::error("Auto-Reject CR job aborted: Admin user 'admin@te.eg' not found.");

            return;
        }

        $configuration = Configuration::where('configuration_name', 'Division Manager Approval')->first();
        $configurationValue = (int) ($configuration->configuration_value ?? 0);
        $sevenDaysAgo = Carbon::now()->subDays($configurationValue);

        // Fetch eligible records
        $records = DB::table('change_request_statuses')
            ->where('new_status_id', 22)
            ->where('active', '1')
            ->where('created_at', '<', $sevenDaysAgo)
            ->get();

        $approvedCount = 0;

        foreach ($records as $record) {
            $crId = $record->cr_id;
            $user_id = $record->user_id;

            $requestData = new \Illuminate\Http\Request([
                'old_status_id' => '22',
                'new_status_id' => '35',
                'user_id' => $adminUser->id,
                'cron_status_log_message' => 'Change request rejected by :user_name due to no response from the requester’s division manager.',
            ]);

            try {
                Log::info("Auto-Reject user: {$user_id}, CR ID: {$crId}");
                $repo->update($crId, $requestData);
                $approvedCount++;
            } catch (Exception $e) {
                Log::error("Failed to update CR ID {$crId}: " . $e->getMessage());
            }
        }

        Log::info("Auto Rejetion CR job finished. Total Rejected: {$approvedCount}");
        $this->info("Auto Rejetion CR job finished. Total Rejected: {$approvedCount}");
    }
}
