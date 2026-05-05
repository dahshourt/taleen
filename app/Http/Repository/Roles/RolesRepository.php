<?php

namespace App\Http\Repository\Roles;

use App\Contracts\Roles\RolesRepositoryInterface;
// declare Entities
use Spatie\Permission\Models\Role;

class RolesRepository implements RolesRepositoryInterface
{
    public function create($request)
    {

        // $data = $request->all();

        $role = Role::create(['name' => $request['role']]);

        if (isset($request['permissions'])) {
            $role->syncPermissions($request['permissions']);
        }

        return $role;
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
        return Role::where('name', '!=', 'Super Admin')
                ->orderBy('id')  // or any other unique, sequential field
                ->paginate(10);
    }

    public function find($id)
    {
        return Role::where('name', '!=', 'Super Admin')->find($id);
    }

    public function getAll()
    {

        return Role::with('parent')->get();
    }

    public function update($request, $id)
    {

        $role = Role::findOrFail($id);
        $role->update(['name' => $request['role']]);
        if (isset($request['permissions'])) {

            $role->syncPermissions($request['permissions']);
        } else {

            $role->syncPermissions([]);
        }
    }

    public function show($id)
    {
        $role = $this->find($id);
        $permissions = $role->permissions;

        return [
            'role' => $role,
            'permissions' => $permissions,
        ];

    }

    public function findByName($name)
    {
        return Role::where('name', '!=', 'Super Admin')->where('name', $name)->first();
    }
}
