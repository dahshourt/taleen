<?php

namespace App\Http\Repository\Workflow;

use App\Contracts\Workflow\WorkflowDependRepositoryInterface;
// declare Entities
use App\Models\DependWorkflow;

class WorkflowDependRepository implements WorkflowDependRepositoryInterface
{
    public function getAll()
    {
        return DependWorkflow::with('depend_workflow_from_status', 'depend_workflow_depend_status')->get();
    }

    public function create($request)
    {

        foreach ($request['depend_status_id'] as $key => $value) {
            DependWorkflow::create([
                'to_status_id' => $request['to_status_id'],
                'depend_status_id' => $request['depend_status_id'][$key],

            ]);

        }
    }

    public function delete($id)
    {
        return DependWorkflow::destroy($id);
    }

    public function update($request, $id)
    {
        return DependWorkflow::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return DependWorkflow::with('depend_workflow_from_status', 'depend_workflow_depend_status')->find($id);
    }
}
