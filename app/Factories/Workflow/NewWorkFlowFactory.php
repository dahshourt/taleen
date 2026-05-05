<?php

namespace App\Factories\Workflow;

use App\Contracts\FactoryInterface;
use App\Http\Repository\NewWorkflow\NewWorkflowRepository;

class NewWorkFlowFactory implements FactoryInterface
{
    public static function index()
    {
        return new NewWorkflowRepository();
    }
}
