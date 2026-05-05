<?php

namespace App\Listeners;

use App\Services\Notification\NotificationService;

class SendChangeRequestNotifications
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $this->notificationService->handleEvent($event);
    }
}
