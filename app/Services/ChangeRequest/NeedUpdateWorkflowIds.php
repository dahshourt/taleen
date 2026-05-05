<?php

namespace App\Services\ChangeRequest;

use App\Models\Change_request as ChangeRequest;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Trait for handling Need Update workflow ID resolution.
 *
 * This trait provides getAllNeedUpdateWorkflowIds() which returns ALL workflow IDs
 * for "Need Update" transitions from ALL parallel statuses (SA, Vendor, Business, Draft CR Doc)
 * to "Pending Create Agreed Scope".
 *
 * IMPORTANT: This replaces the old getNeedUpdateWorkflowId() single-ID approach
 * which only returned the FIRST matching workflow ID and missed transitions from
 * other parallel statuses.
 */
trait NeedUpdateWorkflowIds
{
    /**
     * Get ALL workflow IDs for "Need Update" transitions dynamically.
     *
     * Returns an array of workflow IDs from ALL parallel statuses to "Pending Create Agreed Scope".
     * This ensures "Need Update" is correctly detected when triggered from ANY of the
     * three approval statuses (SA, Vendor, Business) or Request Draft CR Doc.
     *
     * Previously, getNeedUpdateWorkflowId() returned only ONE workflow ID (the first match),
     * but each source status has its OWN workflow ID. When the user selected "Need Update"
     * from Vendor or Business, the request's workflow ID didn't match the first-found one,
     * so the Need Update logic was bypassed and fell through to normal workflow processing.
     *
     * @param  int  $changeRequestId  The Change Request ID
     * @return array Array of workflow IDs that represent "Need Update" transitions
     */
    private function getAllNeedUpdateWorkflowIds(int $changeRequestId): array
    {
        try {
            $changeRequest = ChangeRequest::find($changeRequestId);
            if (! $changeRequest) {
                Log::alert('Change request not found for Need Update workflow lookup', [
                    'cr_id' => $changeRequestId,
                ]);

                return [];
            }

            // Get the target status: "Pending Create Agreed Scope"
            $toStatusId = $this->getStatusIdByName('Pending Create Agreed Scope');
            if (! $toStatusId) {
                Log::alert('Target status "Pending Create Agreed Scope" not found for Need Update', [
                    'cr_id' => $changeRequestId,
                ]);

                return [];
            }

            // All possible source statuses for "Need Update" transition
            $fromStatusNames = [
                'Pending Agreed Scope Approval-SA',
                'Pending Agreed Scope Approval-Vendor',
                'Pending Agreed Scope Approval-Business',
                'Request Draft CR Doc',
            ];

            $workflowIds = [];

            foreach ($fromStatusNames as $statusName) {
                $fromStatusId = $this->getStatusIdByName($statusName);
                if (! $fromStatusId) {
                    Log::debug('Source status not found for Need Update lookup', [
                        'cr_id' => $changeRequestId,
                        'status_name' => $statusName,
                    ]);

                    continue;
                }

                $workflowId = $this->getWorkflowIdByStatusTransition(
                    $changeRequest->workflow_type_id,
                    $fromStatusId,
                    $toStatusId
                );

                if ($workflowId) {
                    $workflowIds[] = $workflowId;

                    Log::info('Found Need Update workflow ID', [
                        'cr_id' => $changeRequestId,
                        'from_status' => $statusName,
                        'from_status_id' => $fromStatusId,
                        'to_status_id' => $toStatusId,
                        'workflow_id' => $workflowId,
                    ]);
                }
            }

            /*Log::info('All Need Update workflow IDs collected', [
                'cr_id' => $changeRequestId,
                'workflow_ids' => $workflowIds,
                'count' => count($workflowIds)
            ]);*/

            return $workflowIds;

        } catch (Exception $e) {
            Log::error('Error getting all Need Update workflow IDs', [
                'cr_id' => $changeRequestId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
