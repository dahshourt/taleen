<?php

// Listener for notifications:

namespace App\Listeners;

use App\Events\ChangeRequestStatusUpdated;
use App\Http\Controllers\MailController;

class SendChangeRequestNotification
{
    public function __construct(private MailController $mailController) {}

    public function handle(ChangeRequestStatusUpdated $event): void
    {
        // Handle specific notification logic based on status changes
        if ($event->oldStatusId == 99 && $event->newStatusId == 101) {
            $this->mailController->notifyCrManager($event->changeRequestId);
        }
    }
}
