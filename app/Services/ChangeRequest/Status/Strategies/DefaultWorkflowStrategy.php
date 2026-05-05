<?php

namespace App\Services\ChangeRequest\Status\Strategies;

use App\Models\Change_request as ChangeRequest;
use App\Models\Change_request_statuse as ChangeRequestStatus;
use App\Models\NewWorkFlow;

class DefaultWorkflowStrategy implements WorkflowStrategyInterface
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
        $cr_status = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $oldStatusId)
            ->whereRaw('CAST(active AS CHAR) != ?', ['0'])
            ->latest()
            ->first();

        if (!$cr_status) {
            // Fallback if no current status found, though logic might require it
            return self::ACTIVE_STATUS;
        }

        $depend_active_statuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('old_status_id', $cr_status->old_status_id)
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->whereNULL('group_id')
            ->whereHas('change_request_data', function ($query) {
                $query->where('workflow_type_id', '!=', 9);
            })
            ->get();

        return $depend_active_statuses->count() > 0 ? self::INACTIVE_STATUS : self::ACTIVE_STATUS;
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
