<?php

namespace App\Listeners;

use App\Events\DefectCreated;
use App\Events\DefectStatusUpdated;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

class SendDefectNotification
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle($event): void
    {
        try {
            $eventType = $event instanceof DefectCreated ? 'created' : 'status_updated';
            
            Log::info("Defect {$eventType} - Processing notification", [
                'defect_id' => $event->defect->id,
                'group_id' => $event->groupId,
            ]);

            $this->notificationService->handleEvent($event);

        } catch (\Exception $e) {
            Log::error('Failed to send defect notification', [
                'defect_id' => $event->defect->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
