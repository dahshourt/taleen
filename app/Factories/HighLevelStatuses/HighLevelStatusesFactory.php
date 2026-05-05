<?php

namespace App\Factories\HighLevelStatuses;

use App\Contracts\FactoryInterface;
use App\Http\Repository\HighLevelStatuses\HighLevelStatusesRepository;

class HighLevelStatusesFactory implements FactoryInterface
{
    public static function index()
    {
        return new HighLevelStatusesRepository();
    }
}
