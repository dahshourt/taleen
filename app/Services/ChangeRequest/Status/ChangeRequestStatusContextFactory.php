<?php

namespace App\Services\ChangeRequest\Status;

use App\Models\Change_request as ChangeRequest;
use App\Models\NewWorkFlow;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ChangeRequestStatusContextFactory
{
    public function build(int $changeRequestId, $request): ChangeRequestStatusContext
    {
        $statusData = $this->extractStatusData($request);
        $changeRequest = $this->getChangeRequest($changeRequestId);
        $workflow = $this->getWorkflow($statusData);
        $userId = $this->getUserId($changeRequest, $request);

        return new ChangeRequestStatusContext(
            $changeRequest,
            $statusData,
            $workflow,
            $userId,
            $request
        );
    }

    private function extractStatusData($request): array
    {
        $newStatusId = $request['new_status_id'] ?? $request->new_status_id ?? null;
        $oldStatusId = $request['old_status_id'] ?? $request->old_status_id ?? null;
        $newWorkflowId = $request['new_workflow_id'] ?? null;

        //if (!$newStatusId || !$oldStatusId) {
        //    throw new InvalidArgumentException('Missing required status IDs');
        //}

        return [
            'new_status_id' => $newStatusId,
            'old_status_id' => $oldStatusId,
            'new_workflow_id' => $newWorkflowId,
        ];
    }

    private function getWorkflow(array $statusData): ?NewWorkFlow
    {
        $workflowId = $statusData['new_workflow_id'] ?: $statusData['new_status_id'];
        return NewWorkFlow::find($workflowId);
    }

    private function getChangeRequest(int $id): ChangeRequest
    {
        $changeRequest = ChangeRequest::find($id);
        if (!$changeRequest) {
            throw new Exception("Change request not found: {$id}");
        }
        return $changeRequest;
    }

    private function getUserId(ChangeRequest $changeRequest, $request): int
    {
        if (Auth::check()) {
            return Auth::id();
        }
        if ($changeRequest->division_manager) {
            $user = User::where('email', $changeRequest->division_manager)->first();
            if ($user) {
                return $user->id;
            }
        }

        $assignedTo = $request['assign_to'] ?? null;
        if (!$assignedTo) {
            throw new Exception('Unable to determine user for status update');
        }

        return $assignedTo;
    }
}
