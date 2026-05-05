<?php

namespace App\Factories\ChangeRequest;

use App\Contracts\FactoryInterface;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;

class ChangeRequestFactory implements FactoryInterface
{
    public static function index()
    {
        return new ChangeRequestRepository();
    }
}
