<?php

namespace App\Http\Controllers\Permissions;

use App\Factories\Permissions\PermissionFactory;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    use ValidatesRequests;

    private $group;

    public function __construct(PermissionFactory $permission)
    {
        // Ensure the user is Super Admin so he can Access the permissions
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            if ($this->user->hasRole('Super Admin')) {
                return $next($request);
            }

            abort(403, 'This action is unauthorized.');

        });

        $this->permission = $permission::index();
        $this->view = 'permissions';
        $view = 'permissions';
        $route = 'permissions';
        $OtherRoute = 'permission';

        $title = 'Permissions';
        $form_title = 'Permission';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index(Request $request)
    {
        $this->authorize('List Permissions'); // permission check

        $collection = $this->permission->getAll();

        return view("$this->view.index", compact('collection'));

    }

    public function create()
    {
        $this->authorize('Create Permission'); // permission check
        $permissions_parents = Permission::with('parent')->whereNull('parent_id')->get();

        return view("$this->view.create", compact('permissions_parents'));
    }

    public function all()
    {
        $groups = $this->group->getAll();

        return view("$this->view.all", compact('groups'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function permission_group($group)
    {
        $pathh = $this->permission->permission_group($group);

        return response()->json(['data' => $pathh], 200);

    }

    public function store(Request $request)
    {
        $this->authorize('Create Permission'); // permission check

        $store = $this->permission->store_permission($request->all());

        return redirect()->back()->with('permission', 'Created Successfully');
    }
    // Back to the administrator to edit or delete permissions
    
    public function edit($id)
    {
        $this->authorize('Edit Permission'); // permission check
        $row = $this->permission->find($id);
        //$row = Permission::with('parent')->find($id);
        $permissions_parents = Permission::with('parent')->whereNull('parent_id')->get();

        return view("$this->view.edit",compact('row' , 'permissions_parents'));

    } //end method


    public function update(Request $request ,$id)
    {
        $this->authorize('Edit Permission'); // permission check
        $this->permission->update($request->all() , $id);
        return redirect()->back()->with('status' , 'Permission Updated Successfully' );
    } //end method


    public function destroy($id) {
        $this->authorize('Delete Permission'); // permission check
        $this->permission->delete($id);
        return redirect()->back()->with('success', 'Permission deleted successfully!');
    } //end method  

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getpath($path)
    {
        // dd($path);
        $pathh = $this->permission->get_path($path);

        return response()->json(['data' => $pathh], 200);
    }
}
