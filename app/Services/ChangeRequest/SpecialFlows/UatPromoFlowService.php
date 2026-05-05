<?php

namespace App\Services\ChangeRequest\SpecialFlows;

use App\Models\Change_request_statuse as ChangeRequestStatus;
use App\Models\Status;
use App\Models\NewWorkFlow;
use App\Models\Change_request;
use Illuminate\Support\Facades\Log;
use App\Http\Repository\ChangeRequest\ChangeRequestStatusRepository;
use Auth;
class UatPromoFlowService
{
    private const WORKFLOW_TYPE_PROMO = 9;

    /**
     * Handle logic to deactivate 'Pending UAT (promo)' status if parallel statuses are active.
     * 
     * @param int $crId Change Request ID
     * @param array $statusData Current status transition data
     * @param int $workflowTypeId Workflow Type ID of the change request
     * @return string|null Returns '0' or '1' if status was changed, null otherwise
     */
    public function handlePendingUatuActivation(int $crId, array $statusData, int $workflowTypeId): ?string
    {
        // 1. Check if Workflow Type is 9
        if ($workflowTypeId != self::WORKFLOW_TYPE_PROMO) {
            return null;
        }

        // 2. Check transition
        $workflowId = $statusData['new_workflow_id'] ?: $statusData['new_status_id'];
        $workflow = NewWorkFlow::find($workflowId);

        $newStatusId = null;
        if ($workflow && $workflow->workflowstatus->isNotEmpty()) {
            $newStatusId = $workflow->workflowstatus->first()->to_status_id;
        }

        $newStatusName = $this->getStatusNameById($newStatusId);
        $oldStatusName = $this->getStatusNameById($statusData['old_status_id']);

        $pendingUatPromoStatus = config('change_request.uat_promo_flow.statuses.pending_uat_promo');
        $deployOnUatEnvStatus = config('change_request.uat_promo_flow.statuses.deploy_on_uat_env');
        $pendingUatTestCaseApprovalStatus = config('change_request.uat_promo_flow.statuses.pending_uat_test_case_approval');

        $isTargetStatusMatch = ($newStatusName === $pendingUatPromoStatus);

        if (!$isTargetStatusMatch) {
            return null;
        }

        // Logic 1: From "Deploy on UAT Environment"
        if ($oldStatusName === $deployOnUatEnvStatus) {
            Log::info('UatPromoFlowService: Transition from Deploy on UAT Environment detected', ['cr_id' => $crId]);

            if ($this->hasActiveParallelStatuses($crId)) {
                $this->updateStatusActive($crId, $newStatusId, '0');
                return '0';
            } else {
                $this->updateStatusActive($crId, $newStatusId, '1');
                return '1';
            }
        }
        // Logic 2: From "Pending UAT Test Cases Approval"
        elseif ($oldStatusName === $pendingUatTestCaseApprovalStatus) {
            Log::info('UatPromoFlowService: Transition from Pending UAT Test Cases Approval detected', ['cr_id' => $crId]);

            $this->updateApprovedCaseStatusActive($crId, $newStatusId, '0');
            $PromoOldStatusId = $this->getStatusIdByName(statusName: $deployOnUatEnvStatus);
            $PromoNewStatusId = $this->getStatusIdByName($pendingUatPromoStatus);

            $this->updateDependStatusActive($crId, $PromoOldStatusId, $PromoNewStatusId);

            return '0';
        } else {
            // Logic 3: Any other transition to "Pending UAT (promo)" -> Force Active 1
            Log::info('UatPromoFlowService: Other transition to Pending UAT (promo) detected - Forcing Active', ['cr_id' => $crId]);

            $this->updateStatusActive($crId, $newStatusId, '1');
            return '1';
        }

        return null;
    }

    /**
     * Check if any of the specific parallel statuses are active for this CR.
     */
    private function hasActiveParallelStatuses(int $crId): bool
    {
        $triggerStatuses = config('change_request.uat_promo_flow.trigger_parallel_statuses', []);

        // Get IDs for the status names
        $statusIds = Status::whereIn('status_name', $triggerStatuses)
            ->pluck('id')
            ->toArray();

        if (empty($statusIds)) {
            Log::warning('UatPromoFlowService: Trigger statuses not found in DB', [
                'names' => $triggerStatuses
            ]);
            return false;
        }

        // Check if any of these are active for the CR
        $exists = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('new_status_id', $statusIds)
            ->where('active', '1') // Assuming '1' is active
            ->exists();

        Log::info('UatPromoFlowService: Parallel status check result', [
            'cr_id' => $crId,
            'exists' => $exists,
            'status_ids' => $statusIds
        ]);

        return $exists;
    }

    private function updateApprovedCaseStatusActive(int $crId, int $statusId, string $active): void
    {

        $affected = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $statusId)
            ->update(['active' => $active]);

        Log::info('UatPromoFlowService: Status update result', [
            'cr_id' => $crId,
            'affected_rows' => $affected
        ]);
    }

    /**
     * Update active status for the Pending UAT (promo) status
     */
    private function updateStatusActive(int $crId, int $statusId, string $active): void
    {
        Log::info('UatPromoFlowService: Attempting to update status active flag', [
            'cr_id' => $crId,
            'status_id' => $statusId,
            'target_active' => $active
        ]);

        $parkedIds = array_values(config('change_request.promo_parked_status_ids', []));



        if (in_array($statusId, $parkedIds, true)) {

            $depend_active_count = ChangeRequestStatus::where('cr_id', $crId)
                ->where('active', '1')
                ->count();


            if (!$this->hasActiveParallelStatuses($crId)) {
                $active = $depend_active_count > 1 ? '0' : '1';
            }
        }

        $affected = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $statusId)
            // ->where('active', '1') // Remove this check to allow updating from any state
            ->orderBy('id', 'desc') // Ensure we target the latest one
            ->limit(1)
            ->update(['active' => $active]);

        Log::info('UatPromoFlowService: Status update result', [
            'cr_id' => $crId,
            'affected_rows' => $affected
        ]);
    }


    private function updateDependStatusActive(int $crId, int $oldStatusId, int $newStatusId): void
    {
        Log::info('UatPromoFlowService: Attempting to update status active flag', [
            'cr_id' => $crId,
            'old_status_id' => $oldStatusId,
            'new_status_id' => $newStatusId
        ]);


        $parkedIds = array_values(config('change_request.promo_parked_status_ids', []));
        $active = '0';

        if (in_array($newStatusId, $parkedIds, true)) {

            $depend_active_count = ChangeRequestStatus::where('cr_id', $crId)
                ->where('active', '1')
                ->count();


            if (!$this->hasActiveParallelStatuses($crId)) {
                $active = $depend_active_count > 0 ? '0' : '1';
            }
        }


        $affected = ChangeRequestStatus::where('cr_id', $crId)
            ->where('old_status_id', $oldStatusId)
            ->where('new_status_id', $newStatusId)
            ->orderBy('id', 'desc')->limit(1)
            ->update(['active' => $active]);
        if (!$affected && $active == '1') {
            $previous_group_id = session('current_group') ?: (auth()->check() ? auth()->user()->default_group : null);

            $newStatusRow = Status::find($newStatusId);
            $changeRequest = Change_request::find($crId);
            $current_group_id = $newStatusRow->GetViewGroup($changeRequest->application_id);
            if ($current_group_id) {
                $current_group_id = $current_group_id->id;
            } else {
                $current_group_id = optional($newStatusRow->group_statuses)
                    ->where('type', '2')
                    ->pluck('group_id')
                    ->first();
            }
            $statusRepository = new ChangeRequestStatusRepository();
            $payload = $this->buildStatusData(
                $crId,
                $oldStatusId,
                $newStatusId,
                null,
                $currentStatus->reference_group_id ?? 8,
                $previous_group_id,
                $current_group_id,
                Auth::id(),
                $active
            );


            $statusRepository->create($payload);

        }
        Log::info('UatPromoFlowService: Status update result', [
            'cr_id' => $crId,
            'affected_rows' => $affected
        ]);
    }

    public function getStatusNameById($statusId)
    {
        $status = Status::find($statusId);
        return $status ? $status->status_name : null;
    }

    private function getStatusIdByName($statusName)
    {
        $status = Status::where('status_name', $statusName)->first();
        return $status ? $status->id : null;
    }

    private function buildStatusData(
        int $changeRequestId,
        int $oldStatusId,
        int $newStatusId,
        ?int $group_id,
        ?int $reference_group_id,
        ?int $previous_group_id,
        ?int $current_group_id,
        int $userId,
        string $active
    ): array {
        $status = Status::find($newStatusId);
        $sla = $status ? (int) $status->sla : 0;

        return [
            'cr_id' => $changeRequestId,
            'old_status_id' => $oldStatusId,
            'new_status_id' => $newStatusId,
            'group_id' => $group_id,
            'reference_group_id' => $reference_group_id,
            'previous_group_id' => $previous_group_id,
            'current_group_id' => $current_group_id,
            'user_id' => $userId,
            'sla' => $sla,
            'active' => $active, // '0' | '1' | '2'
        ];
    }

}
