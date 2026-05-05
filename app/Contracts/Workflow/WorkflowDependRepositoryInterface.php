<?php

namespace App\Contracts\Workflow;

interface WorkflowDependRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);
}
