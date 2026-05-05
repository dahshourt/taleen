<?php

namespace App\Http\Repository\Statuses;

use App\Contracts\Statuses\StatusWorkFlowRepositoryInterface;
// declare Entities
use App\Models\StatusWorkFlow;

class StatusWorkFlowRepository implements StatusWorkFlowRepositoryInterface
{
    public function getAll()
    {
        return StatusWorkFlow::with('from_status', 'to_status', 'from_stage', 'to_stage')->get();
    }

    public function create($request)
    {
        foreach ($request['to_status_id'] as $key => $value) {
            StatusWorkFlow::updateOrCreate(
                ['from_status_id' => $request['from_status_id'], 'to_status_id' => $value],
                ['type' => $request['type'], 'from_stage_id' => $request['from_stage_id'], 'to_stage_id' => $request['to_stage_id']]
            );
        }

        return true;

    }

    public function delete($id)
    {
        return StatusWorkFlow::destroy($id);
    }

    public function update($request, $id)
    {
        $status = StatusWorkFlow::where('id', $id)->update($request);

        return $status;
    }

    public function find($id)
    {
        return StatusWorkFlow::with('from_status', 'to_status', 'from_stage', 'to_stage')->find($id);
    }
}
