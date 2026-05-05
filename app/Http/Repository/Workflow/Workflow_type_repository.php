<?php

namespace App\Http\Repository\Workflow;

use App\Contracts\Workflow\workflow_type_contracts;
// declare Entities
use App\Models\WorkFlowType;
use Illuminate\Support\Collection;

class Workflow_type_repository implements workflow_type_contracts
{
    public function get_workflow_type()
    {

        return WorkFlowType::select('id', 'name', 'parent_id')->where('parent_id', null)->get();
    }

    public function get_workflow_subtype($id)
    {

        return WorkFlowType::select('id', 'name', 'parent_id')->where('parent_id', $id)->get();
    }

    public function get_workflow_all_subtype()
    {

        return WorkFlowType::select('id', 'name', 'parent_id')->WhereNotNull('parent_id')->get();
    }

    public function get_workflow_all_subtype_without_release()
    {

        return WorkFlowType::select('id', 'name', 'parent_id')->WhereNotNull('parent_id')->where('id', '<', 6)->get();
    }

    public function get_all_active_workflow(): Collection
    {
        return WorkFlowType::active()->select('id', 'name', 'parent_id')->WhereNotNull('parent_id')->get();
    }

    public function getWorkflowsForListCRs(): Collection
    {
        return WorkFlowType::active()->select(['id', 'name'])
            ->WhereNotNull('parent_id')
            //->whereIn('id', [3, 5, 9])
            ->get();
    }
}
