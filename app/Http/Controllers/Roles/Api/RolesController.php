<?php

namespace App\Http\Controllers\Roles\Api;

use App\Factories\Roles\RolesFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\RolesRequest\Api\RolesRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Foundation\Validation\ValidatesRequests;

class RolesController extends Controller
{
    use ValidatesRequests;

    private $role;

    public function __construct(RolesFactory $role)
    {

        $this->role = $role::index();

    }

    public function index()
    {
        $roles = $this->role->list();
        $roles = RoleResource::collection($roles);

        return response()->json(['data' => $roles], 200);

    }

    public function all() {}

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(RolesRequest $RolesRequest)
    {

        $roles = $this->role->create($RolesRequest->all());
        if ($roles) {
            return response()->json(['message' => 'Created Succefully'], 200);
        }
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(RolesRequest $RolesRequest, $id)
    {
        $roles = $this->role->update($RolesRequest->except('_method'), $id);
        if ($roles) {
            return response()->json(['message' => 'Updated Succefully'], 200);
        }
    }

    public function show($id)
    {
        $role = $this->role->show($id);
        $role = new RoleResource($role);

        return response()->json(['data' => $role], 200);
    }

    public function destroy($id)
    {
        $roles = $this->role->delete($id);
        if ($roles) {
            return response()->json(['message' => 'Deleted Succesfully'], 200);
        }
    }
}
