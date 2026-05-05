<?php

namespace App\Http\Controllers\Roles;

use App\Factories\Roles\RolesFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\RolesRequest\RolesRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
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

        $title = 'roles';
        $form_title = 'role';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {

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
        //

        $roles = $this->role->list();

        return view("$this->view.create", compact('roles'));
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
        $this->role->create($request->all());

        return redirect()->route('roles.index')->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $row = $this->role->find($id);

        $roles = $this->role->list();

        return view("$this->view.edit", compact('row', 'roles'));

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

        $this->role->update($request->except(['_token', '_method']), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
    }

    public function show($id)
    {
        $role = $this->role->find($id);
        if (! $role) {
            return response()->json([
                'message' => 'User Not Exists',
            ], 422);
        }
        $role = new UsersResource($role);

        return response()->json(['data' => $role], 200);
    }

    public function destroy() {}

    public function updateactive(Request $request)
    {

        $data = $this->role->find($request->id);

        $this->role->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);

    }
}
