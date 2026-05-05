<?php

namespace App\Http\Repository\Users;

use App\Contracts\Users\UserRepositoryInterface;
use App\Http\Repository\Roles\RolesRepository;
// declare Entities
use App\Models\Application;
use App\Models\Change_request;
use App\Models\Pivotusersrole;
use App\Models\SystemUserCab;
use App\Models\User;
use App\Models\SystemTeValidator;
use App\Models\UserGroups;
use Arr;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public $dev_users = ['ahmed.elfeel', 'sara.mostafa', 'mahmoud.bastawisy', 'tarek.kamaleldin', 'walid.dahshour', 'ahmed.o.hasan'];

    public function StoreUserGroups($user_id, $request)
    {
        $groups = $request['group_id'];
        $default_group = $request['default_group'];
        if (!in_array($default_group, $groups)) { // check if default group not in groups array then add it to groups array
            $groups[] = $default_group;
        }
        if (isset($user_id) && !empty($groups)) {
            UserGroups::where('user_id', $user_id)->delete();
            foreach ($groups as $value) {
                $user_groups = new UserGroups;
                $user_groups->user_id = $user_id;
                $user_groups->group_id = $value;
                $user_groups->save();
            }
        }

        return true;
    }

    public function getAll()
    {
        return User::with('defualt_group', 'user_report_to.user')->get();
    }

    public function getAllWithActive()
    {
        return User::with('defualt_group', 'user_report_to.user')->where('active', '1')->get();
    }

    public function paginateAll()
    {
        return User::orderBy('id', 'DESC')->with('defualt_group')->get();
    }

    public function create($request)
    {

        if (!isset($request['email'])) {
            $request['email'] = $request['user_name'] . '@te.eg';

        }
        $request['flag'] = '1';
        // All users are now AD users (user_type = 1)
        $request['user_type'] = '1';
        $user = User::create($request);

        // Old Roles

        if (!empty($request['all_users_roles_values'])) {
            $report_to = $request['all_users_roles_values'];
        } else {
            $report_to = $user->id;
        }

        Pivotusersrole::create(['report_to' => $report_to, 'user_id' => $user->id]);

        unset($request->all_users_roles_values);

        // end old roles

        // new roles and permissions using spatie

        if (isset($request['roles'])) {
            // dd($request['roles']);
            $user->assignRole($request['roles']);
        }

        if (isset($request['permissions'])) {
            // dd($request['permissions']);
            $user->givePermissionTo($request['permissions']);
        }

        // end new roles and permissions
        $this->StoreUserGroups($user->id, $request);

        return $user;
    }

    public function delete($id)
    {
        return User::destroy($id);
    }

    public function update($request, $id)
    {
        $user = User::findOrFail($id);

        if (!empty($request['all_users_roles_values'])) {
            $report_to = $request['all_users_roles_values'];
        } else {
            $report_to = $id;
        }

        // Pivotusersrole::where('user_id',$id)->update([ 'report_to' => $report_to, 'user_id' => $id]);
        Pivotusersrole::updateOrCreate([
            // Add unique field combo to match here
            // For example, perhaps you only want one entry per user:
            'user_id' => $id,
        ], [
            'report_to' => $report_to,
            'user_id' => $id,
        ]);

        // All users are AD users, no password handling needed
        $except = ['group_id', '_method', 'password', 'password_confirmation', 'all_users_roles_values', 'roles', 'permissions', 'user_type'];

        if (isset($request['active']) && $request['active'] == 1) {
            $request['failed_attempts'] = '1';
        }

        // Ensure user_type remains 1 (AD user)
        $request['user_type'] = '1';

        $filteredRequest = Arr::except($request, $except);
        $user = User::where('id', $id)->update($filteredRequest);

        // new roles and permissions using spatie
        $user = User::findOrFail($id);

        if (isset($request['roles'])) {
            $user->syncRoles([]);
            $user->assignRole($request['roles']);
        } else {
            if (in_array($user->user_name, $this->dev_users)) {
                $user->syncRoles(['Super Admin']);
            } else {
                $user->syncRoles([]);
            }
        }

        if (isset($request['permissions'])) {
            // dd($request['permissions']);
            $user->syncPermissions($request['permissions']);
        } else {
            $user->syncPermissions([]);
        }

        // end new roles and permissions

        $this->StoreUserGroups($id, $request);

        return $user;
    }

    public function update1($request, $id)
    {
        return User::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return User::with('user_groups.group')->find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update1(['active' => '0'], $id);
        }

        return $this->update1(['active' => '1', 'failed_attempts' => '1'], $id);

    }

    public function get_users_with_group_and_role($role_id, $default_group)
    {
        $role = new RolesRepository;
        $parent_role = $role->show($role_id);
        if ($parent_role) {
            $parent_role_id = $parent_role->parent_id;
        } else {
            $parent_role_id = 0;
        }

        // return User::where('default_group',$default_group)->where('role_id','>',$role_id)->get();
        return User::where('default_group', $default_group)->where('role_id', $parent_role_id)->get();
    }

    public function get_user_by_department_id($id)
    {
        return User::where('department_id', $id)->where('active', '1')->get();
    }

    public function get_user_by_department_ids(array $id)
    {
        return User::active()->whereIn('department_id', $id)->get();
    }

    public function get_user_by_group_id($id)
    {
        $users = User::where(function ($query) use ($id) {
            $query->where('default_group', $id);
            $query->where('active', '1');
        })
            ->orwhereHas('user_groups', function ($query) use ($id) {
                $query->where('group_id', $id);
            })->get();

        return $users;
        // return User::where('default_group', $id)->where('active', '1')->get();
    }

    public function get_user_by_group($app_id)
    {
        $app_groups = Application::find($app_id)->group_applications()->pluck('group_id')->toArray();

        $users = User::where(function ($query) use ($app_groups) {
            $query->whereIn('default_group', $app_groups);
            $query->where('active', '1');
        })
            ->orwhereHas('user_groups', function ($query) use ($app_groups) {
                $query->whereIn('group_id', $app_groups);
            })->get();

        return $users;

        // dd($app_groups);
        // return User::where('department_id', $id)->get();
        // return User::whereIn('default_group', $app_groups)->where('active', '1')->get();
    }

    public function get_parent_cr_user($parent_CR)
    {
        $parentCRUser = Change_request::find($parent_CR);
        $user_id = $parentCRUser->developer_id;
        $user = User::where('id', $user_id)->where('active', '1')->get();

        return $user;
    }

    /*  public function find($id)
    {
       return User::with('user_groups.group')->find($id);
    }
*/

    public function CheckUniqueEmail($email): bool
    {
        return User::where('email', $email)->exists();

    }

    public function get_users_cap($system_id)
    {
        return SystemUserCab::where('system_id', $system_id)->where('active', '1')->whereHas('user', function ($query) {
            $query->where('active', '1');
        })->get();
    }

    public function GetAssignmentUsersByViewGroups($group_ids)
    {
        $users = User::where(function ($query) use ($group_ids) {
            $query->whereIn('default_group', $group_ids);
            $query->where('active', '1');
        })
            ->orwhereHas('user_groups', function ($query) use ($group_ids) {
                $query->whereIn('group_id', $group_ids);
            })->get();

        return $users;
        // return User::where('default_group', $id)->where('active', '1')->get();
    }

    public function get_users_te_validators($system_id = null)
    {
        $query = SystemTeValidator::where('active', '1')
            ->whereHas('user', function ($query) {
                $query->where('active', '1');
            });

        if ($system_id) {
            $query->where('system_id', $system_id)->orWhere('system_id', null);
        }

        return $query->with('user')->get();
    }
}
