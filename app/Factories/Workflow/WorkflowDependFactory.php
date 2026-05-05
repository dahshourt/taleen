<?php

namespace App\Factories\Workflow;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Workflow\WorkflowDependRepository;

class WorkflowDependFactory implements FactoryInterface
{
    public static function index()
    {
        return new WorkflowDependRepository();
    }
}
