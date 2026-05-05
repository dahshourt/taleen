<?php

namespace App\Factories\Applications;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Applications\ApplicationRepository;

class ApplicationFactory implements FactoryInterface
{
    public static function index()
    {
        return new ApplicationRepository();
    }
}
