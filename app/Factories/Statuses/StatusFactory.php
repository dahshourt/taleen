<?php

namespace App\Factories\statuses;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Statuses\StatusRepository;

class StatusFactory implements FactoryInterface
{
    public static function index()
    {
        return new StatusRepository();
    }
}
