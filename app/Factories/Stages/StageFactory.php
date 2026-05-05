<?php

namespace App\Factories\stages;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Stages\StageRepository;

class StageFactory implements FactoryInterface
{
    public static function index()
    {
        return new StageRepository();
    }
}
