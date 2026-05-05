<?php

namespace App\Events;

use App\Models\Change_request;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Events\Dispatchable;

class NotifyOperationalDM
{
    use Dispatchable;

    public function __construct(public Change_request $cr)
    {
        $this->handle();
    }

    private function handle(): void
    {
        app(NotificationService::class)->notifyOperationalDM($this->cr);
    }
}
