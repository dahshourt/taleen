<?php

namespace App\Services\ChangeRequest\Status\Strategies;

use App\Models\Change_request as ChangeRequest;
use App\Models\NewWorkFlow;

interface WorkflowStrategyInterface
{
    /**
     * Determine the active status for the new workflow step.
     *
     * @param int $changeRequestId
     * @param object $workflowStatus
     * @param NewWorkFlow $workflow
     * @param int $oldStatusId
     * @param int $newStatusId
     * @param ChangeRequest $changeRequest
     * @return string
     */
    public function determineActiveStatus(
        int $changeRequestId,
        $workflowStatus,
        NewWorkFlow $workflow,
        int $oldStatusId,
        int $newStatusId,
        ChangeRequest $changeRequest
    ): string;
    /**
     * Determine if the workflow status should be skipped.
     *
     * @param ChangeRequest $changeRequest
     * @param object $workflowStatus
     * @param array $statusData
     * @return bool
     */
    public function shouldSkipWorkflowStatus(
        ChangeRequest $changeRequest,
        $workflowStatus,
        array $statusData
    ): bool;
}
