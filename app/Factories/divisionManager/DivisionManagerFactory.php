<?php

namespace App\Factories\divisionManager;

use App\Contracts\FactoryInterface;
use App\Http\Repository\divisionManager\DivisionManagerRepository;

class DivisionManagerFactory implements FactoryInterface
{
    public static function index()
    {
        return new DivisionManagerRepository();
    }
}
