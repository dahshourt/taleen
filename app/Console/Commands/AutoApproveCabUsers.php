<?php

namespace App\Console\Commands;

use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Models\CabCrUser;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AutoApproveCabUsers extends Command
{
    protected $signature = 'cab:approve-users';

    protected $description = 'Automatically approve cab_cr_users after 2 days if not approved';

    public function handle()
    {
        $thresholdDate = $this->getThresholdDate(2);

        $pendingCabStatusId = \App\Services\StatusConfigService::getStatusId('pending_cab') ?? 38;
        $pendingCabApprovalStatusId = \App\Services\StatusConfigService::getStatusId('pending_cab_approval');

        $validStatusIds = array_filter([$pendingCabStatusId, $pendingCabApprovalStatusId]);

        // Get users who are not approved and older than 2 days
        $users = CabCrUser::where('status', '0')
            ->whereHas('cabCr', function ($query) use ($validStatusIds) {
                $query->where('status', '0')
                    ->whereHas('change_request', function ($crQuery) use ($validStatusIds) {
                        $crQuery->whereHas('currentRequestStatuses', function ($statusQuery) use ($validStatusIds) {
                            $statusQuery->whereIn('new_status_id', $validStatusIds);
                        });
                    });
            })
            ->with('cabCr')
            ->where('created_at', '<=', $thresholdDate)
            ->get();

        $repo = new ChangeRequestRepository();
        $approvedCount = 0;

        foreach ($users as $user) {
            $crId = $user->cabCr->cr_id ?? null;

            if (! $crId) {
                Log::warning("CabCrUser ID {$user->id} has no associated CR ID.");

                continue;
            }

            $currentStatus = \App\Models\Change_request_statuse::where('cr_id', $crId)
                ->where('active', '1')
                ->value('new_status_id');

            if (! $currentStatus || ! in_array($currentStatus, $validStatusIds)) {
                Log::warning("CR ID {$crId} has current status {$currentStatus} which is not a valid pending CAB status. Skipping.");

                continue;
            }

            $cr = \App\Models\Change_request::find($crId);
            if (! $cr) {
                Log::warning("CR ID {$crId} not found in database. Skipping.");

                continue;
            }

            $workflowTypeId = $cr->getSetStatus()->where('workflow_type', '0')->pluck('id')->first();

            if (! $workflowTypeId) {
                Log::warning("CR ID {$crId} could not determine new status ID for the next step. Skipping.");

                continue;
            }

            $requestData = new Request([
                'old_status_id' => $currentStatus,
                'new_status_id' => $workflowTypeId,
                'cab_cr_flag' => '1',
                'user_id' => $user->user_id,
                'cron_status_log_message' => ":prefix Approved by System due to CAB member no response. That is considered as a passive approval and the status changed to ':status_name'",
            ]);

            try {
                $repo->update($crId, $requestData);
                Log::info("Auto-approved user {$user->user_id} for CR ID: {$crId}. Status changed from {$currentStatus} to {$workflowTypeId}.");
                $approvedCount++;
            } catch (Exception $e) {
                Log::error("Failed to update CR ID {$crId}: " . $e->getMessage() . "\n" . $e->getLine() . "\n" . $e->getFile());
            }
        }

        $this->info("Auto-approved $approvedCount user(s).");
    }

    private function getThresholdDate(int $daysToSubtract): Carbon
    {
        $thresholdDate = Carbon::now();

        while ($daysToSubtract > 0) {
            $thresholdDate->subDay();

            // Skip Friday (5) and Saturday (6) according to ISO-8601 (Mon=1, Sun=7)
            if (! in_array($thresholdDate->dayOfWeekIso, [5, 6])) {
                $daysToSubtract--;
            }
        }

        return $thresholdDate;
    }
}
