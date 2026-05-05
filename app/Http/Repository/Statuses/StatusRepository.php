<?php

namespace App\Http\Repository\Statuses;

use App\Contracts\Statuses\StatusRepositoryInterface;
use App\Models\GroupStatuses;
// declare Entities
use App\Models\Status;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatusRepository implements StatusRepositoryInterface
{
    public static function getAll()
    {
        return Status::with('group_statuses', 'stage', 'group_statuses.group')->get();
    }

    public static function getAllActive(): Collection
    {
        return Status::active()->with('group_statuses', 'stage', 'group_statuses.group')->get();
    }

    public function paginateAll(array $relations = [])
    {
        $query = Status::latest();
        if (count($relations) > 0) {
            $query = $query->with($relations);
        }

        return $query->get();
    }

    public function create($request)
    {
        $status = Status::create($request);
        $this->StoreStatusGroup($status->id, $request);

        return $status;
    }

    public function StoreStatusGroup($status_id, $request)
    {
        // Always delete existing set groups first
        GroupStatuses::where('status_id', $status_id)->where('type', 1)->delete();
        
        // Add new set groups if provided
        if (isset($request['set_group_id']) && ! empty($request['set_group_id'])) {
            foreach ($request['set_group_id'] as $key => $value) {
                $group_statuses = new GroupStatuses;
                $group_statuses->status_id = $status_id;
                $group_statuses->group_id = $value;
                $group_statuses->type = 1;
                $group_statuses->save();
            }
        }

        // Always delete existing view groups first
        GroupStatuses::where('status_id', $status_id)->where('type', 2)->delete();
        
        // Add new view groups if provided
        if (isset($request['view_group_id']) && ! empty($request['view_group_id'])) {
            foreach ($request['view_group_id'] as $key => $value) {
                $group_statuses = new GroupStatuses;
                $group_statuses->status_id = $status_id;
                $group_statuses->group_id = $value;
                $group_statuses->type = 2;
                $group_statuses->save();
            }
        }

        return true;
    }

    public function delete($id)
    {
        return Status::destroy($id);
    }

    public function update($request, $id)
    {

        $status = Status::where('id', $id)->update($request->except('set_group_id', 'view_group_id', '_method',
            '_token'));

        $this->StoreStatusGroup($id, $request);

        return $status;
    }

    public function update1($request, $id)
    {

        $status = Status::where('id', $id)->update($request);

        return $status;
    }

    public function find($id)
    {
        return Status::with('group_statuses', 'stage', 'group_statuses.group')->find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {

            return $this->update1(['active' => '0'], $id);
        }

        return $this->update1(['active' => '1'], $id);

    }

    public function get_crs_group_by_status_OLD($status_req, $workflow_type_req)
    {
        dd(json_encode($status_req));
        $new_sta = trim(json_encode($status_req), '[]');

        return DB::select("
            SELECT
            COUNT(cr.id) 'CRs_Count', (select st.status_name from  statuses st where st.id in(cr_status.new_status_id))  'Status_Name'
        FROM
            change_request AS cr

            left join change_request_statuses as cr_status on cr.id = cr_status.cr_id

            where cr_status.active = 1
            AND cr_status.new_status_id IN($new_sta)
            AND cr.workflow_type_id = \"$workflow_type_req\"
            group by cr_status.new_status_id
        ");
    }

    public function get_crs_group_by_status($status_req, $workflow_type_req, $applications_req = null)
    {
        $new_app = implode(', ', $applications_req);
        $new_sta = trim(json_encode($status_req), '[]');

        return DB::select("
            SELECT
            COUNT(cr.id) 'CRs_Count', (select st.status_name from  statuses st where st.id in(cr_status.new_status_id))  'Status_Name'
        FROM
            change_request AS cr

            left join change_request_statuses as cr_status on cr.id = cr_status.cr_id

            where cr_status.active = 1
            AND cr_status.new_status_id IN($new_sta)
            AND cr.workflow_type_id = \"$workflow_type_req\"
            AND cr.application_id IN($new_app)
            group by cr_status.new_status_id
        ");
    }

    public function get_defect_status()
    {
        return Status::whereIn('status_name', ['Pending', 'Solved', 'Not Defect'])->get();
    }

    public function get_defect_status_by_id($status_id)
    {
        return Status::where('id', $status_id)->first();
    }
}
