<?php

namespace App\Http\Repository\RequesterDepartment;

use App\Contracts\RequesterDepartment\RequesterDepartmentRepositoryInterface;
use App\Models\RequesterDepartment;

class RequesterDepartmentRepository implements RequesterDepartmentRepositoryInterface
{
    public function getAll()
    {
        return RequesterDepartment::all();
    }

    public function create($request)
    {
        return RequesterDepartment::create($request);
    }

    public function delete($id)
    {
        return RequesterDepartment::destroy($id);
    }

    public function update($request, $id)
    {
        return RequesterDepartment::where('id', $id)->update($request);
    }

    public function paginateAll()
    {
        return RequesterDepartment::latest()->paginate(10);
    }

    public function find($id)
    {
        return RequesterDepartment::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);
    }
}
