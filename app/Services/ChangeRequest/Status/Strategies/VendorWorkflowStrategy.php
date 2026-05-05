<?php

namespace App\Services\ChangeRequest\Status\Strategies;

use App\Models\Change_request as ChangeRequest;
use App\Models\Change_request_statuse as ChangeRequestStatus;
use App\Models\NewWorkFlow;

class VendorWorkflowStrategy implements WorkflowStrategyInterface
{
    protected const ACTIVE_STATUS = '1';
    protected const INACTIVE_STATUS = '0';

    public function determineActiveStatus(
        int $changeRequestId,
        $workflowStatus,
        NewWorkFlow $workflow,
        int $oldStatusId,
        int $newStatusId,
        ChangeRequest $changeRequest
    ): string {
        $parkedIds = array_values(config('change_request.vendor_parked_status_ids', []));
        $NextStatusWorkflow = NewWorkFlow::find($newStatusId);

        // Safety check if workflow not found
        if (!$NextStatusWorkflow) {
            return self::ACTIVE_STATUS;
        }

        // Logic specific to Vendor Workflow
        if (isset($NextStatusWorkflow->workflowstatus[0]) && in_array($NextStatusWorkflow->workflowstatus[0]->to_status_id, $parkedIds, true)) {
            $depend_active_statuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
                ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
                ->count();

            return $depend_active_statuses > 0 ? self::INACTIVE_STATUS : self::ACTIVE_STATUS;
        }

        return self::ACTIVE_STATUS;
    }

    /**
     * Determine if the workflow status should be skipped.
     */
    public function shouldSkipWorkflowStatus(
        ChangeRequest $changeRequest,
        $workflowStatus,
        array $statusData
    ): bool {
        // Skip design status if design duration is 0
        return $changeRequest->design_duration == '0'
            && $workflowStatus->to_status_id == 40
            && $statusData['old_status_id'] == 74;
    }
}
