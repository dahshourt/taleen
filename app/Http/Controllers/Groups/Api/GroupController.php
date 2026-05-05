<?php

namespace App\Http\Controllers\Groups\Api;

use App\Factories\Groups\GroupFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\Api\GroupRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class GroupController extends Controller
{
    use ValidatesRequests;

    private $group;

    public function __construct(GroupFactory $group)
    {

        $this->group = $group::index();

    }

    public function index()
    {
        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;
        $groups = $this->group->getAllWithFilter($parent_id);

        return response()->json(['data' => $groups], 200);
    }

    public function all()
    {

        $groups = $this->group->getAll();

        return response()->json(['data' => $groups], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(GroupRequest $request)
    {
        $this->group->create($request->all());

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
    public function update(GroupRequest $request, $id)
    {
        $group = $this->group->find($id);
        if (! $group) {
            return response()->json([
                'message' => 'Group Not Exists',
            ], 422);
        }
        $this->group->update($request, $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $group = $this->group->find($id);

        return response()->json(['data' => $group], 200);
    }

    public function destroy() {}

    public function updateactive($id)
    {

        $group = $this->group->find($id);

        $this->group->updateactive($group['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);

    }
}
