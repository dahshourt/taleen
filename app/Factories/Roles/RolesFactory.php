<?php

namespace App\Factories\Roles;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Roles\RolesRepository;

class RolesFactory implements FactoryInterface
{
    public static function index()
    {
        return new RolesRepository();
    }
}
