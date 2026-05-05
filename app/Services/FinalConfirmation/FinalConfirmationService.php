<?php

namespace App\Services\FinalConfirmation;

use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Repository\FinalConfirmation\FinalConfirmationRepository;
use App\Services\ChangeRequest\ChangeRequestUpdateService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FinalConfirmationService
{
    private $finalConfirmationRepository;

    private $changeRequestRepository;

    public function __construct(
        FinalConfirmationRepository $finalConfirmationRepository,
        ChangeRequestRepository $changeRequestRepository
    ) {
        $this->finalConfirmationRepository = $finalConfirmationRepository;
        $this->changeRequestRepository = $changeRequestRepository;
    }

    /**
     * Process final confirmation action (reject/cancel)
     */
    public function processFinalConfirmation(string $crNumber, int $statusId, int $userId, string $technical_feedback): array
    {
        try {
            // Find the change request by number
            $changeRequest = $this->finalConfirmationRepository->findCRByNumber($crNumber);

            if (! $changeRequest) {
                return [
                    'success' => false,
                    'message' => "Change Request #{$crNumber} not found.",
                ];
            }

            // Get current status
            $currentStatus = $this->finalConfirmationRepository->getCurrentStatus($changeRequest->id);

            if (! $currentStatus) {
                return [
                    'success' => false,
                    'message' => "Unable to determine current status for CR #{$crNumber}.",
                ];
            }

            if ($changeRequest->inFinalState()) {
                return [
                    'success' => false,
                    'message' => "CR #{$crNumber} is already in a final state and cannot be modified.",
                ];
            }

            if ($changeRequest->isAlreadyCancelledOrRejected()) {
                return [
                    'success' => false,
                    'message' => "CR #{$crNumber} is already Cancelled or Rejected and cannot be modified.",
                ];
            }

            // Update the change request status
            $updateResult = $this->updateChangeRequestStatus(
                $changeRequest->id,
                $currentStatus->new_status_id,
                $statusId,
                $userId,
                $technical_feedback
            );

            if ($updateResult) {

                $actionText = $statusId === $this->getRejectStatusId() ? 'rejected' : 'cancelled';

                return [
                    'success' => true,
                    'message' => "CR #{$crNumber} has been successfully {$actionText}.",
                ];
            }

            return [
                'success' => false,
                'message' => "Failed to update CR #{$crNumber} status. Please try again.",
            ];

        } catch (Exception $e) {
            Log::error('Error in processFinalConfirmation', [
                'cr_number' => $crNumber,
                'action' => $statusId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred while processing the final confirmation.',
            ];
        }
    }

    private function getRejectStatusId(): int
    {
        return \App\Services\StatusConfigService::getStatusId('Reject');
    }

    private function getCancelStatusId(): int
    {
        return \App\Services\StatusConfigService::getStatusId('Cancel');
    }

    /**
     * Update change request status using the existing repository pattern
     */
    private function updateChangeRequestStatus(int $crId, int $oldStatusId, int $newStatusId, int $userId, string $technical_feedback): bool
    {
        try {

            $action_name = $newStatusId === 19 ? 'rejected' : 'cancelled';

            $workflows_reject_and_cancel_id = config('change_request.workflows_reject_and_cancel_id');

            $updateRequest = new Request([
                'old_status_id' => $oldStatusId,
                'new_status_id' => $workflows_reject_and_cancel_id[$newStatusId],
                'user_id' => $userId,
                'is_final_confirmation' => true,
                'action' => $action_name,
            ]);

            app(ChangeRequestRepository::class)->UpateChangeRequestStatus($crId, $updateRequest);

            $cr_update_service = app(ChangeRequestUpdateService::class);

            $request_data = new Request([
                'technical_feedback' => $technical_feedback,
            ]);

            $cr_update_service->updateCRData($crId, $request_data);

            return true;

        } catch (Exception $e) {
            Log::error('Error updating change request status in final confirmation', [
                'cr_id' => $crId,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
