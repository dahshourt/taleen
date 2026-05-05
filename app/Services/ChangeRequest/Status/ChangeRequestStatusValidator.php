<?php

namespace App\Services\ChangeRequest\Status;

use App\Models\Change_request as ChangeRequest;
use App\Models\Change_request_statuse as ChangeRequestStatus;
use App\Models\NewWorkFlow;
use Exception;

class ChangeRequestStatusValidator
{
    private static ?int $PENDING_CAB_STATUS_ID = null;

    public static function init()
    {
        self::$PENDING_CAB_STATUS_ID = \App\Services\StatusConfigService::getStatusId('pending_cab');
    }

    /**
     * Validate the status change
     */
    /**
     * Validate the status change
     */
    public function validateStatusChange(ChangeRequestStatusContext $context): bool
    {
        // 1. Check if status is actually changing
        if ($context->statusData['old_status_id'] == $context->statusData['new_status_id']) {
            return false;
        }

        // 2. Check workflow dependencies
        if (!$this->checkWorkflowDependencies($context->changeRequest->id, $context->workflow->workflowstatus->first())) {
            // ...
        }

        return true;
    }

    /**
     * Check workflow dependencies
     */
    public function checkWorkflowDependencies(int $changeRequestId, $workflowStatus): bool
    {
        if (!$workflowStatus || !$workflowStatus->dependency_ids) {
            return true;
        }

        $dependencyIds = array_diff(
            $workflowStatus->dependency_ids,
            [$workflowStatus->new_workflow_id]
        );

        foreach ($dependencyIds as $workflowId) {
            if (!$this->isDependencyMet($changeRequestId, $workflowId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a specific dependency is met
     */
    public function isDependencyMet(int $changeRequestId, int $workflowId): bool
    {
        $dependentWorkflow = NewWorkFlow::find($workflowId);

        if (!$dependentWorkflow) {
            return false;
        }

        return ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $dependentWorkflow->from_status_id)
            ->where('old_status_id', $dependentWorkflow->previous_status_id)
            ->where('active', '2') // Completed
            ->exists();
    }

    public function isTransitionFromPendingCab(ChangeRequest $changeRequest, array $statusData): bool
    {
        if (self::$PENDING_CAB_STATUS_ID === null) {
            return false;
        }

        $workflow = NewWorkFlow::where('from_status_id', self::$PENDING_CAB_STATUS_ID)
            ->where('type_id', $changeRequest->workflow_type_id)
            ->where('workflow_type', '0') // Normal workflow (not reject)
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->first();

        if (!$workflow) {
            return false;
        }

        /*return isset($statusData['old_status_id']) &&
               (int)$statusData['old_status_id'] === self::$PENDING_CAB_STATUS_ID;*/
        return isset($statusData['new_status_id']) &&
            (int) $statusData['new_status_id'] === $workflow->id;
    }
}
