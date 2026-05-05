<?php

namespace App\Factories\Permissions;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Permissions\ModuleRolesRepository;

class ModuleRoulesFactory implements FactoryInterface
{
    public static function index()
    {
        return new ModuleRolesRepository();
    }
}
