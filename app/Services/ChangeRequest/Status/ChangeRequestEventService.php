<?php

namespace App\Services\ChangeRequest\Status;

use App\Events\ChangeRequestStatusUpdated;
use App\Events\CrDeliveredEvent;
use App\Models\NewWorkFlow;
use Illuminate\Support\Facades\Log;

class ChangeRequestEventService
{
    private static ?int $DELIVERED_STATUS_ID = null;
    private static ?int $REJECTED_STATUS_ID = null;

    public function __construct()
    {
        self::$DELIVERED_STATUS_ID = \App\Services\StatusConfigService::getStatusId('Delivered');
        self::$REJECTED_STATUS_ID = \App\Services\StatusConfigService::getStatusId('Reject');
    }

    public function fireStatusUpdated(ChangeRequestStatusContext $context, string $activeFlag): void
    {
        event(new ChangeRequestStatusUpdated(
            $context->changeRequest,
            $context->statusData,
            $context->request,
            $activeFlag
        ));
    }

    public function checkAndFireDeliveredEvent(ChangeRequestStatusContext $context): void
    {
        $newWorkflowId = $context->statusData['new_status_id'] ?? null;
        if (!$newWorkflowId) {
            return;
        }

        Log::info('Checking for delivered event', [
            'change_request_id' => $context->changeRequest->id,
            'new_workflow_id' => $newWorkflowId,
        ]);

        $workflow = NewWorkFlow::with('workflowstatus')->find($newWorkflowId);
        if (!$workflow) {
            return;
        }

        foreach ($workflow->workflowstatus as $wfStatus) {
            if (in_array((int) $wfStatus->to_status_id, [self::$DELIVERED_STATUS_ID, self::$REJECTED_STATUS_ID], true)) {
                $context->changeRequest->refresh();

                Log::info('Firing CrDeliveredEvent', [
                    'cr_id' => $context->changeRequest->id,
                    'cr_no' => $context->changeRequest->cr_no,
                ]);

                event(new CrDeliveredEvent($context->changeRequest));
            }
        }
    }
}
