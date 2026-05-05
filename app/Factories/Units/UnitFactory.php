<?php

namespace App\Factories\Units;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Units\UnitRepository;

class UnitFactory implements FactoryInterface
{
    public static function index()
    {
        return new UnitRepository();
    }
}
