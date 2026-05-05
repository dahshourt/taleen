<?php

namespace App\Factories\Parents;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Parents\ParentRepository;

class ParentFactory implements FactoryInterface
{
    public static function index()
    {
        return new ParentRepository();
    }
}
