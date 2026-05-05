<?php

namespace App\Http\Repository\Permissions;

use App\Contracts\Permissions\PermissionRepositoryInterface;
// declare Entities
// use App\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function getAll()
    {
        return Permission::paginate(10);
    } // end method

    public function store_permission($request)
    {

        $validator = Validator::make($request, [
            'permission' => 'required|unique:permissions,name',
            'permission_module' => 'required',
            'permission_parent' => 'nullable|exists:permissions,id',

        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        Permission::create(['name' => $request['permission'], 'module' => $request['permission_module'], 'parent_id' => $request['permission_parent'] ?? null]);  //

        // return true;
        return redirect()->back()->with('status', 'Permission Added Successfully');

    }  // end method

    public function find($id)
    {
        return Permission::find($id);
    } // end method

    public function list()
    {
        return Permission::all();
    } // end method

    public function update($request, $id)
    {

        $validator = Validator::make($request, [
            'permission' => 'required|unique:permissions,name,' . $id,

        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        Permission::where('id', $id)->update(['name' => $request['permission'], 'module' => $request['permission_module'], 'parent_id' => $request['permission_parent'] ?? null]);

    } // end method

    public function delete($id)
    {
        return Permission::find($id)->delete();
    } // end method
}
