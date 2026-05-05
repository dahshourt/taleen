<?php

namespace App\Http\Repository\Applications;

use App\Contracts\Applications\ApplicationRepositoryInterface;
// declare Entities
use App\Models\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApplicationRepository implements ApplicationRepositoryInterface
{
    public function getAllWithFilter($parent_id = null)
    {
        if ($parent_id) {
            return Application::with('parent')->whereNotNull('parent_id')->get();
        }

        return Application::with('children')->whereNull('parent_id')->get();

    }

    public function getAll()
    {
        return Application::all();
    }

    public function getAllActive(): Collection
    {
        return Application::active()->get();
    }

    public function paginateAll()
    {
        return Application::latest()->paginate(10);
    }

    public function create($request)
    {

        if (isset($request['app_file'])) {
            $file = $this->UploadSystemFile($request['app_file']);
            $request['file'] = $file;
            unset($request['app_file']);
        }
        // dd($request);
        if (! isset($request['active'])) {
            $request['active'] = '0';
        }
        $application = Application::create($request);

        return $application->id;
    }

    public function delete($id)
    {
        return Application::destroy($id);
    }

    public function update($request, $id)
    {

        if (isset($request['app_file'])) {
            $file = $this->UploadSystemFile($request['app_file']);
            $request['file'] = $file;
        }
        unset($request['app_file']);
        if (! isset($request['active'])) {
            $request['active'] = '0';
        }

        return Application::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Application::find($id);
    }

    public function workflowType($id)
    {
        $application = Application::find($id);

        return $application->workflow_type;
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }

    public function application_based_on_workflow($workflowTypeId)
    {
        return Application::where('workflow_type_id', $workflowTypeId)->get();
    }

    public function get_app_id_by_name($name)
    {
        return Application::where('name', $name)->first();
    }

    public function get_crs_group_bu_applications($applications_req, $workflow_type_req)
    {

        if (empty($applications_req)) {
            $all_apps = $this->getAll();
            foreach ($all_apps as $item) {
                $applications_req[] = "$item->id";
            }
            $new_app = trim(json_encode($applications_req), '[]');
        } else {
            $new_app = trim(json_encode($applications_req), '[]');
        }

        return DB::select("
                SELECT
                    COUNT(cr.id) 'CRs_Count',
                      (select `name` from applications where applications.id = cr.application_id) `application_name`

                FROM
                    change_request AS cr
                    where  cr.workflow_type_id = \"$workflow_type_req\"
                     AND cr.application_id IN($new_app)
                GROUP BY cr.application_id;
             ");

    }

    public function UploadSystemFile($app_file)
    {
        $filename = time() . '.' . $app_file->getClientOriginalExtension();
        $original_file_name = $app_file->getClientOriginalName();
        $app_file->move(public_path() . '/uploads/', $original_file_name);
        $file_path = public_path() . '/uploads/' . $original_file_name;

        return $original_file_name;
    }
}
