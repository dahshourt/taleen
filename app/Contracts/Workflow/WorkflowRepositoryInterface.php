<?php

namespace App\Contracts\Workflow;

interface WorkflowRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);
}
