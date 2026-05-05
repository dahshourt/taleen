<?php

namespace App\Http\Repository\Workflow;

use App\Contracts\Workflow\WorkflowRepositoryInterface;
// declare Entities
use App\Models\Status;
use App\Models\Workflow;

class WorkflowRepository implements WorkflowRepositoryInterface
{
    public function getAll()
    {
        return Workflow::all();
    }

    public function paginateAll()
    {
        return Workflow::latest()->get();
    }

    public function create($request)
    {

        if (isset($request['to_status_label'])) {
            $to_status_label = $request['to_status_label'];
        } else {
            $to_status_label = '';
        }
        if (isset($request['default_to_status'])) {
            $request['default_to_status'] = $request['default_to_status'];
        } else {
            $request['default_to_status'] = 1;
        }
        if (is_array($request['to_status_id'])) {
            // dd('yes');
            foreach ($request['to_status_id'] as $key => $value) {
                Workflow::create([
                    'from_status_id' => $request['from_status_id'],
                    'from_status_name' => Status::find($request['from_status_id'])->status_name,
                    'to_status_id' => $value,
                    'to_status_name' => Status::find($value)->status_name,
                    'to_status_label' => isset($request['to_status_label']) ? $request['to_status_label'] : '',
                    'default_to_status' => $request['default_to_status'],
                ]);

            }
        } else {
            // dd('no');
            Workflow::create([
                'from_status_id' => $request['from_status_id'],
                'from_status_name' => Status::find($request['from_status_id'])->status_name,
                'to_status_id' => $request['to_status_id'],
                'to_status_name' => Status::find($request['to_status_id'])->status_name,
                'to_status_label' => isset($request['to_status_label']) ? $request['to_status_label'] : '',
                'default_to_status' => $request['default_to_status'],
            ]);
        }

    }

    public function delete($id)
    {
        return Workflow::destroy($id);
    }

    public function update1($request, $id)
    {

        $status = Workflow::where('id', $id)->update($request);

        return $status;
    }

    public function updateactive($active, $id)
    {
        if ($active) {

            return $this->update1(['active' => '0'], $id);
        }

        return $this->update1(['active' => '1'], $id);

    }

    public function update($request, $id)
    {

        $from_status_name = Status::find($request['from_status_id'])->status_name;
        $to_status_name = Status::find($request['to_status_id'])->status_name;
        $request['from_status_name'] = $from_status_name;
        $request['to_status_name'] = $to_status_name;

        return Workflow::where('id', $id)->update($request);
    }

    public function find($id)
    {

        return Workflow::find($id);
    }

    public function listFromStatuses($id)
    {
        return Workflow::select('from_status_id')->with('from_status')->where('to_status_id', $id)->get();
    }

    public function getFirstCreationStatus()
    {

        return Workflow::select('from_status_id')->whereColumn('from_status_id', '=', 'to_status_id')
            ->groupBy('from_status_id', 'to_status_id')
            ->get();
    }
}
