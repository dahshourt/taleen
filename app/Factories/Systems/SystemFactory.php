<?php

namespace App\Factories\Systems;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Systems\SystemRepository;

class SystemFactory implements FactoryInterface
{
    public static function index()
    {
        return new SystemRepository();
    }
}
