<?php

namespace App\Http\Repository\ChangeRequest;

use App\Contracts\ChangeRequest\ChangeRequestStatusRepositoryInterface;
// use Debugbar;
// declare Entities
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\Status;
use Auth;

class ChangeRequestStatusRepository implements ChangeRequestStatusRepositoryInterface
{
    public function getAll($group)
    {

        $status = Change_request::with('Req_status.status')->
        whereHas('Req_status.status.group_statuses', function ($q) use ($group) {

            $q->where('group_id', '=', $group);
            $q->where('type', '=', 2);

        })->get();

        return $status;
    }

    public function list_CRS()
    {

        $crs = Change_request::select('id', 'cr_no')->with('Req_status.status')->get();

        return $crs;
    }

    public function create($request)
    {
        return Change_request_statuse::create($request);
    }

    public function update_status($request, $id, $user_id)
    {
        Change_request_statuse::where('cr_id', $id)
            ->where('old_status_id', $request->old_status_id)
            ->update(['active' => '2']);
        $this->create([
            'old_status_id' => $request->old_status_id,
            'new_status_id' => $request->new_status_id,
            'cr_id' => $id,
            'user_id' => $user_id,
        ]);
    }

    public function createInitialStatus($cr_id, $request)
    {
        $status_sla = Status::find($request['new_status_id']);
        if ($status_sla) {
            $status_sla = $status_sla->sla;
        } else {
            $status_sla = 0;
        }
        $user_id = Auth::user()->id; // 3;
        $data = [
            'cr_id' => $cr_id,
            'old_status_id' => $request['old_status_id'],
            'new_status_id' => $request['new_status_id'],
            'sla' => $status_sla,
            'user_id' => $user_id,
            // 'updated_at' => NULL,
            'active' => '1',
        ];
        $this->create($data);

        return true;
    }
}
