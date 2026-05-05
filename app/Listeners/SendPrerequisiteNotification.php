<?php

namespace App\Listeners;

use App\Events\PrerequisiteCreated;
use App\Events\PrerequisiteStatusUpdated;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

class SendPrerequisiteNotification
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle($event): void
    {
        try {
            $eventType = $event instanceof PrerequisiteCreated ? 'created' : 'status_updated';
            
            Log::info("Prerequisite {$eventType} - Processing notification", [
                'prerequisite_id' => $event->prerequisite->id,
                'group_id' => $event->groupId,
            ]);

            $this->notificationService->handleEvent($event);

        } catch (\Exception $e) {
            Log::error('Failed to send prerequisite notification', [
                'prerequisite_id' => $event->prerequisite->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
