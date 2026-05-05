<?php

namespace App\Http\Repository\Parents;

use App\Contracts\Parents\ParentRepositoryInterface;
// declare Entities
use App\Models\Application;
use App\Models\Change_request;
use App\Models\Parents_crs;
use Illuminate\Support\Collection;

class ParentRepository implements ParentRepositoryInterface
{
    public function paginateAll()
    {
        return Parents_crs::latest()->paginate(10);
    }

    public function get_parent_subtype($id)
    {

        $application = Parents_crs::select('application_name')->where('id', $id)->first();

        return Application::select('name', 'id')->where('name', $application->application_name)->get();
    }

    public function getAll()
    {
        return Parents_crs::all();
    }

    public function getAllActive(): Collection
    {
        return Parents_crs::active()->get();
    }

    public function parent_systems($system)
    {
        $application = Parents_crs::select('application_name')->where('name', $system)->first();

        return Application::select('name', 'id')->where('name', $application->application_name)->get();

    }

    public function create($request)
    {

        $change_request = Change_request::find($request['name']);

        $application = Application::find($change_request->application_id);

        $data['application_name'] = $application->name;

        $data['name'] = $request['name'];
        $data['active'] = '1';
        if (isset($request['approval_file'])) {
            $file = $this->UploadParentFile($request['approval_file']);
            $data['file'] = $file;
            unset($request['approval_file']);
        }

        return Parents_crs::create($data);
    }

    public function delete($id)
    {
        return Parents_crs::destroy($id);
    }

    public function update($request, $id)
    {
        if (isset($request['approval_file'])) {
            $file = $this->UploadParentFile($request['approval_file']);
            $request['file'] = $file;
        }
        unset($request['approval_file']);

        return Parents_crs::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Parents_crs::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }

    public function UploadParentFile($approval_file)
    {
        $filename = time() . '.' . $approval_file->getClientOriginalExtension();
        $original_file_name = $approval_file->getClientOriginalName();
        $approval_file->move(public_path() . '/uploads/', $original_file_name);
        $file_path = public_path() . '/uploads/' . $original_file_name;

        return $original_file_name;
    }
}
