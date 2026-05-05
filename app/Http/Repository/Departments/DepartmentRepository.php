<?php

namespace App\Http\Repository\Departments;

use App\Contracts\Departments\DepartmentRepositoryInterface;
// declare Entities
use App\Models\Department;

class DepartmentRepository implements DepartmentRepositoryInterface
{
    public function getAll()
    {
        return Department::all();
    }

    public function create($request)
    {
        return Department::create($request);
    }

    public function delete($id)
    {
        return Department::destroy($id);
    }

    public function update($request, $id)
    {
        return Department::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Department::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }
}
