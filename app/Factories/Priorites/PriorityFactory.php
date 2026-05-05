<?php

namespace App\Factories\Priorites;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Priorities\PriorityRepository;

class PriorityFactory implements FactoryInterface
{
    public static function index()
    {
        return new PriorityRepository();
    }
}
