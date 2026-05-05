<?php

namespace App\Factories\SystemTeValidator;

use App\Contracts\FactoryInterface;
use App\Http\Repository\SystemTeValidator\SystemTeValidatorRepository;

class SystemTeValidatorFactory implements FactoryInterface
{
    public static function index()
    {
        return new SystemTeValidatorRepository();
    }
}
