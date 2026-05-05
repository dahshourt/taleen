<?php

namespace App\Factories\Departments;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Departments\DepartmentRepository;

class DepartmentFactory implements FactoryInterface
{
    public static function index()
    {
        return new DepartmentRepository();
    }
}
