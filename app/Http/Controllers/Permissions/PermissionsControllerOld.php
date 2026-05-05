<?php

namespace App\Http\Controllers\Permissions;

use App\Factories\Permissions\PermissionFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Permissions\ModuleRolesRepository;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    use ValidatesRequests;

    private $group;

    public function __construct(PermissionFactory $permission)
    {

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
        //  dd("l");

        $collection = $this->permission->getAll();

        // dd($collection);
        return view("$this->view.index", compact('collection'));

    }

    public function create()
    {
        //

        $permissions = $this->permission->getAll();
        $groups = (new GroupRepository)->getAll();
        $rules = (new ModuleRolesRepository)->getAll();

        return view("$this->view.create", compact('permissions', 'groups', 'rules'));
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

        $store = $this->permission->store_permission($request->all());

        // dd($store);
        return redirect()->back()->with('permission', 'Created Successfully');
    }

    public function edit($id)
    {
        $row = $this->permission->find($id);
        $permissions = $this->permission->getAll();
        $groups = (new GroupRepository)->getAll();
        $rules = (new ModuleRolesRepository)->getAll();

        return view("$this->view.edit", compact('row', 'permissions', 'groups', 'rules'));

    } // end method

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
