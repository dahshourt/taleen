<?php

namespace App\Console\Commands;

use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\DirectorApprovalCr;
use App\Models\DirectorApprovalCrUser;
use App\Services\StatusConfigService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DirectorsApprovalsCommand extends Command
{
    protected $signature = 'directors:approvals';

    protected $description = 'If no response from directors is received within 2 working hours, the system will automatically record a passive confirmation and move the CR to the next status';

    public function handle(): void
    {
        $directors_approvals_status_id = StatusConfigService::getStatusId('cr_directors_approvals');

        $director_users = DirectorApprovalCrUser::whereHas('directorApprovalCr', function ($query) use ($directors_approvals_status_id) {
            $query->where('status', DirectorApprovalCr::INACTIVE)
                ->whereHas('change_request', function ($query) use ($directors_approvals_status_id) {
                    $query->whereHas('currentRequestStatuses', function ($statusQuery) use ($directors_approvals_status_id) {
                        $statusQuery->where('new_status_id', $directors_approvals_status_id);
                    });
                });
        })
            ->with('directorApprovalCr')
            ->where('status', DirectorApprovalCrUser::INACTIVE)
            ->where('created_at', '<=', now()->subHours(2))
            ->get();

        $cr_repo = app(ChangeRequestRepository::class);

        foreach ($director_users as $director_user) {

            $crId = $director_user->directorApprovalCr->cr_id ?? null;

            if (! $crId) {
                Log::warning("DirectorApprovalCr ID {$director_user->id} has no associated CR ID.");

                continue;
            }

            $currentStatus = Change_request_statuse::where('cr_id', $crId)
                ->where('active', '1')
                ->value('new_status_id');

            if (! $currentStatus || $currentStatus !== $directors_approvals_status_id) {
                Log::warning("CR ID {$crId} has current status {$currentStatus} which is not a valid directorApprovalCr status. Skipping.");

                continue;
            }

            $cr = Change_request::find($crId);
            if (! $cr) {
                Log::warning("CR ID {$crId} not found in database. Skipping.");

                continue;
            }

            $workflowTypeId = $cr->getSetStatus()->where('workflow_type', '0')->pluck('id')->first();

            $requestData = new Request([
                'old_status_id' => $currentStatus,
                'new_status_id' => $workflowTypeId,
                'user_id' => $director_user->user_id,
                'cron_status_log_message' => ":prefix Approved by System due to director no response. That is considered as a passive approval and the status changed to ':status_name'",
            ]);

            try {
                $cr_repo->update($crId, $requestData);
                Log::info("Auto-approved director {$director_user->user_id} for CR ID: {$crId}. Status changed from {$currentStatus} to {$workflowTypeId}.");
            } catch (Throwable $e) {
                Log::error("Auto-approved director Failed to update CR ID {$crId}: " . $e->getMessage() . "\n" . $e->getLine() . "\n" . $e->getFile());
            }
        }

        $this->info('Auto-approved director(s).');
    }
}
