<?php

namespace App\Http\Repository\Roles;

use App\Contracts\Roles\RolesRepositoryInterface;
// declare Entities
use App\Models\Role;

class RolesRepository implements RolesRepositoryInterface
{
    public function create($request)
    {
        return Role::create($request);
    }

    public function list()
    {
        return Role::all();
    }

    public function delete($id)
    {
        return Role::find($id)->delete();
    }

    public function paginateAll()
    {
        return Role::latest()->paginate(10);
    }

    public function find($id)
    {
        return Role::with('parent')->find($id);
    }

    public function getAll()
    {
        return Role::with('parent')->get();
    }

    public function update($request, $id)
    {

        return Role::where('id', $id)->update($request);
    }

    public function show($id)
    {
        return Role::where('id', $id)->first();
    }
}
