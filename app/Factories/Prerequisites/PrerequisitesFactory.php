<?php

namespace App\Factories\Prerequisites;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Prerequisites\PrerequisitesRepository;

class PrerequisitesFactory implements FactoryInterface
{
    public static function index()
    {
        return new PrerequisitesRepository();
    }
}
