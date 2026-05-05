<?php

namespace App\Factories\ChangeRequest;

use App\Contracts\FactoryInterface;
use App\Http\Repository\ChangeRequest\NotificationChangeRequestRepository;

class AttachmetsCRSFactory implements FactoryInterface
{
    public static function index()
    {
        return new NotificationChangeRequestRepository();
    }
}
