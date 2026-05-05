<?php

namespace App\Http\Controllers\Permissions\Api;

use App\Factories\Permissions\ModuleRoulesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class Module_RulesController extends Controller
{
    use ValidatesRequests;

    private $group;

    public function __construct(ModuleRoulesFactory $m_role)
    {

        $this->m_role = $m_role::index();

    }

    public function index(Request $request)
    {
        //  dd("l");

        $module_roles = $this->m_role->getAll();

        return response()->json(['data' => $module_roles], 200);

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
    public function getpath($path)
    {
        // dd($path);
        $pathh = $this->permission->get_path($path);

        return response()->json(['data' => $pathh], 200);
    }

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
