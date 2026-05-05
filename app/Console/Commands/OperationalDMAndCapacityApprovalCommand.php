<?php

namespace App\Console\Commands;

use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Models\DeploymentApprovalCr;
use App\Models\DeploymentApprovalCrUser;
use App\Services\StatusConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalDMAndCapacityApprovalCommand extends Command
{
    protected $signature = 'division-managers:approvals';

    protected $description = 'If no response is received from the DM within 4 working hours, the system will automatically record a passive confirmation and proceed to the next step.';

    public function handle(): void
    {
        $dm_users = DeploymentApprovalCrUser::whereHas('deploymentApprovalCr', function ($query) {
            $query->where('status', DeploymentApprovalCr::INACTIVE);
        })
            ->with('deploymentApprovalCr')
            ->where('status', DeploymentApprovalCrUser::INACTIVE)
            ->where('created_at', '<=', now()->subHours(4))
            ->get();

        $cr_repo = app(ChangeRequestRepository::class);

        foreach ($dm_users as $dm_user) {

            $crId = $dm_user->deploymentApprovalCr->cr_id ?? null;

            if (! $crId) {
                Log::warning("DeploymentApprovalCr ID {$dm_user->id} has no associated CR ID.");

                continue;
            }

            try {
                $cr_repo->approveDeployment($crId, $dm_user->user_id);
                Log::info("Auto-approved OperationalDMAndCapacity {$dm_user->user_id} for CR ID: {$crId}.");
            } catch (Throwable $e) {
                Log::error("DeploymentApprovalCr Failed to update CR ID {$crId}: " . $e->getMessage() . "\n" . $e->getLine() . "\n" . $e->getFile());
            }
        }

        $this->info('Auto-approved OperationalDMAndCapacity.');
    }
}
