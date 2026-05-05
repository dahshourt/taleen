<?php

namespace App\Factories\NotificationTemplates;

use App\Contracts\FactoryInterface;
use App\Http\Repository\NotificationTemplates\NotificationTemplatesRepository;

class NotificationTemplatesFactory implements FactoryInterface
{
    public static function index()
    {
        return new NotificationTemplatesRepository();
    }
}
