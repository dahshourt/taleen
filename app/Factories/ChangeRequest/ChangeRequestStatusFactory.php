<?php

namespace App\Factories\ChangeRequest;

use App\Contracts\FactoryInterface;
use App\Http\Repository\ChangeRequest\ChangeRequestStatusRepository;

class ChangeRequestStatusFactory implements FactoryInterface
{
    public static function index()
    {
        return new ChangeRequestStatusRepository();
    }
}
