<?php

namespace App\Factories\RequesterDepartment;

use App\Contracts\FactoryInterface;
use App\Http\Repository\RequesterDepartment\RequesterDepartmentRepository;

class RequesterDepartmentFactory implements FactoryInterface
{
    public static function index()
    {
        return new RequesterDepartmentRepository();
    }
}
