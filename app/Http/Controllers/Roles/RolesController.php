<?php

namespace App\Http\Controllers\Roles;

use App\Factories\Roles\RolesFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\RolesRequest\RolesRequest;
use App\Models\Permission;
use Illuminate\Foundation\Validation\ValidatesRequests;
// use Spatie\Permission\Models\Permission;

use Illuminate\Http\Request;

class RolesController extends Controller
{
    use ValidatesRequests;

    private $role;

    public function __construct(RolesFactory $role)
    {

        $this->role = $role::index();
        $this->view = 'roles';
        $view = 'roles';
        $route = 'roles';
        $OtherRoute = 'role';

        $title = 'Roles';
        $form_title = 'Role';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {
        $this->authorize('List Roles'); // permission check

        $collection = $this->role->paginateAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create Role'); // permission check

        // $permissions = Permission::with('parent')->get()->groupBy('module');
        $permissions = Permission::whereNull('parent_id')->where('module', '!=', 'Permissions')->get();

        // dd($permissions[0]->name);
        return view("$this->view.create", compact('permissions'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(RolesRequest $request)
    {
        $this->authorize('Create Role'); // permission check
        $this->role->create($request->all());

        return redirect()->route('roles.index')->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $this->authorize('Edit Role'); // permission check

        $row = $this->role->find($id);
        // $permissions = Permission::all()->groupBy('module');
        $permissions = Permission::whereNull('parent_id')->where('module', '!=', 'Permissions')->get();
        $role_permissions = $row->permissions->pluck('name')->toArray();

        return view("$this->view.edit", compact('row', 'permissions', 'role_permissions'));

    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(RolesRequest $request, $id)
    {
        $this->authorize('Edit Role'); // permission check

        $this->role->update($request->all(), $id);

        return redirect()->back()->with('status', 'Role Updated Successfully');
    }

    public function show($id)
    {
        $this->authorize('Show Role'); // permission check

        $data = $this->role->show($id);
        $row = $data['role'];
        $permissions = $data['permissions'];

        // dd($permissions);
        return view("$this->view.show", compact('row', 'permissions'));
    }

    public function destroy($id)
    {
        $this->authorize('Delete Role'); // permission check

        $this->role->delete($id);

        return redirect()->back()->with('success', 'Role deleted successfully!');

    }
    /*public function updateactive(Request $request){

        $data = $this->role->find($request->id);

        $this->role->updateactive($data->active,$request->id);

            return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success'
        ]);
    }*/

}
