<?php

namespace App\Factories\NotificationRules;

use App\Contracts\FactoryInterface;
use App\Http\Repository\NotificationRules\NotificationRulesRepository;

class NotificationRulesFactory implements FactoryInterface
{
    public static function index()
    {
        return new NotificationRulesRepository();
    }
}
