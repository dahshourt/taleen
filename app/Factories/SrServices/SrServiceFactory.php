<?php

namespace App\Factories\SrServices;

use App\Contracts\FactoryInterface;
use App\Http\Repository\SrServices\SrServiceRepository;

class SrServiceFactory implements FactoryInterface
{
    public static function index()
    {
        return new SrServiceRepository();
    }
}
