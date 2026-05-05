<?php

namespace App\Http\Controllers\Users\Api;

use App\Factories\Users\UserFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\Api\UserRequest;
use App\Http\Resources\UsersResource;
use Illuminate\Foundation\Validation\ValidatesRequests;

class UserController extends Controller
{
    use ValidatesRequests;

    private $user;

    public function __construct(UserFactory $user)
    {

        $this->user = $user::index();

    }

    public function index()
    {

        $users = $this->user->getAll();

        return response()->json(['data' => $users], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(UserRequest $request)
    {
        $this->user->create($request->all());

        return response()->json([
            'message' => 'Created Successfully',
        ]);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(UserRequest $request, $id)
    {
        $user = $this->user->find($id);
        if (! $user) {
            return response()->json([
                'message' => 'User Not Exists',
            ], 422);
        }
        $this->user->update($request, $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $user = $this->user->find($id);
        if (! $user) {
            return response()->json([
                'message' => 'User Not Exists',
            ], 422);
        }
        $user = new UsersResource($user);

        return response()->json(['data' => $user], 200);
    }

    public function destroy() {}

    public function updateactive($id)
    {
        $user = $this->user->find($id);

        $this->user->updateactive($user['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);

    }

    public function get_users_with_group_and_role($role_id, $default_group)
    {

        $user = $this->user->get_users_with_group_and_role($role_id, $default_group);

        return response()->json([
            'data' => $user,
        ], 200);
    }
}
