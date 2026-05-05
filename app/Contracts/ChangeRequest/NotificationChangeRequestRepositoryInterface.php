<?php

namespace App\Contracts\ChangeRequest;

interface NotificationChangeRequestRepositoryInterface
{
    public function send_notification($id);
}
