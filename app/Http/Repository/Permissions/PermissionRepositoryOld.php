<?php

namespace App\Http\Repository\Permissions;

use App\Contracts\Permissions\PermissionRepositoryInterface;
// declare Entities
use App\Models\Group;
use App\Models\Module;
use App\Models\Permission;
use App\Models\UserGroups;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionRepository implements PermissionRepositoryInterface
{
    public function getAll()
    {
        // $group = request()->header('group');
        $user = Auth::user();
        $user_id = $user->id;
        $groups = UserGroups::select('group_id')->where('user_id', $user->id)->get();
        // $per = Module::whereHas('module_rules', function ($query) use ($groups,$user_id) {
        //     $query->whereHas('permission', function ($query) use ($groups,$user_id) {
        //         $query->Where('user_id', $user_id)->orwhereIn('group_id', $groups);
        //     });
        // })->with('module_rules')->get();

        $per = Permission::all();
        // $per = Module::with(['module_rules' => function ($query) use ($groups,$user_id) {
        //     $query->whereHas('permission', function ($query) use ($groups,$user_id) {
        //         $query->Where('user_id', $user_id)
        //         // ->orWhere('group_id',$group);
        //         ->orwhereIn('group_id', $groups);
        //     });
        // }])->get();

        return $per;
    }

    public function permission_group($group)
    {
        return Permission::with('permision_module_rule')->where('group_id', $group)->get();
    }

    public function get_path($path)
    {
        // dd($path);
        $user = Auth::user();
        // dd($user);
        $user_id = $user->id;
        $groups = UserGroups::select('id')->where('user_id', $user->id)->get();
        // dd($groups);
        //  $group=Auth::user()->default_group;
        $per = Permission::with('permision_module_rule')
            ->where('user_id', $user_id)
            ->orwhereIn('group_id', $groups)
            ->whereHas('permision_module_rule', function ($query) use ($path) {
                $query->where('action_url', '=', $path);
            })->get();

        if (count($per) > 0) {
            return true;
        }

        return false;

    }

    public function store_permission($request)
    {

        $validator = Validator::make($request, [
            'group_id' => 'required|exists:groups,id',
            'rule_id' => 'required|array|min:1',
            'rule_id.*' => 'exists:module_rules,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $p = Permission::where('group_id', $request['group_id'])->first();
        if ($p) {
            Permission::where('group_id', $request['group_id'])->delete();
        }

        foreach ($request['rule_id'] as $module_rule_id) {
            // dd($module_rule_id);
            $p = new Permission();
            $p->module_rule_id = $module_rule_id;
            $p->group_id = $request['group_id'];
            $p->save();

        }

        // return true;
        return redirect()->back()->with('status', 'Added Successfully');

    }  // end method

    public function find($id)
    {
        return Permission::find($id);
    }
}
