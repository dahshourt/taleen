<?php

namespace App\Factories\Defect;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Defect\DefectRepository;

class DefectFactory implements FactoryInterface
{
    public static function index()
    {
        return new DefectRepository();
    }
}
