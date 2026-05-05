<?php

namespace App\Factories\KPIs;

use App\Contracts\FactoryInterface;
use App\Http\Repository\KPIs\KPIRepository;

class KPIFactory implements FactoryInterface
{
    public static function index()
    {
        return new KPIRepository();
    }
}
