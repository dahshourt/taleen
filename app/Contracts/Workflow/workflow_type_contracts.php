<?php

namespace App\Contracts\Workflow;

interface workflow_type_contracts
{
    public function get_workflow_type();

    public function get_workflow_subtype($id);
}
