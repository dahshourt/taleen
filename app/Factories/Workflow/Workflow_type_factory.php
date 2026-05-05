<?php

namespace App\Factories\Workflow;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Workflow\Workflow_type_repository;

class Workflow_type_factory implements FactoryInterface
{
    public static function index()
    {

        return new Workflow_type_repository();
    }
}
