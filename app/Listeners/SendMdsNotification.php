<?php

namespace App\Listeners;

use App\Events\MdsStartDateUpdated;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Listener for MDS start date update notifications.
 * 
 * This listener calls the NotificationService to process
 * notifications when an MDS start date is changed.
 */
class SendMdsNotification
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(MdsStartDateUpdated $event): void
    {
        try {
            Log::info('MDS Start Date Updated - Processing notification', [
                'cr_id' => $event->changeRequest->id,
                'cr_no' => $event->changeRequest->cr_no,
                'group_id' => $event->groupId,
                'old_start_date' => $event->oldStartDate,
                'new_start_date' => $event->newStartDate,
            ]);

            $this->notificationService->handleEvent($event);

        } catch (\Exception $e) {
            Log::error('Failed to send MDS notification', [
                'cr_id' => $event->changeRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
