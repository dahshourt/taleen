<?php

namespace App\Factories\Releases;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Releases\ReleaseRepository;

class ReleaseFactory implements FactoryInterface
{
    public static function index()
    {
        return new ReleaseRepository();
    }
}
