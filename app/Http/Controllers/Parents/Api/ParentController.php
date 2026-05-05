<?php

namespace App\Http\Controllers\Parents\Api;

use App\Factories\Parents\ParentFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Parents\Api\ParentRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class ParentController extends Controller
{
    use ValidatesRequests;

    private $parent;

    public function __construct(ParentFactory $parent)
    {

        $this->parent = $parent::index();

    }

    public function subtype($id)
    {

        $get_parent_subtype = $this->parent->get_parent_subtype($id);

        return response()->json(['data' => $get_parent_subtype], 200);
    }

    public function index()
    {
        $systems = $this->parent->getAll();

        return response()->json(['data' => $systems], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(ParentRequest $request)
    {

        $asd = $this->parent->create($request->all());

        return response()->json([
            'message' => $asd,
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
    public function update(ParentRequest $request, $id)
    {
        $parent = $this->parent->find($id);
        if (! $parent) {
            return response()->json([
                'message' => 'parent Not Exists',
            ], 422);
        }
        $this->parent->update($request->except('_method'), $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $parent = $this->parent->find($id);
        if (! $parent) {
            return response()->json([
                'message' => 'parent Not Exists',
            ], 422);
        }

        return response()->json(['data' => $parent], 200);
    }

    public function StageStatuses($id)
    {
        $parent = $this->parent->find($id);
        if (! $parent) {
            return response()->json([
                'message' => 'parent Not Exists',
            ], 422);
        }

        return response()->json(['data' => $parent->statuses], 200);
    }

    public function parent_systems($system)
    {
        $get_parent_subtype = $this->parent->get_parent_subtype($system);

        return response()->json(['data' => $get_parent_subtype], 200);

    }

    public function destroy() {}

    public function updateactive($id)
    {
        $parent = $this->parent->find($id);

        $this->parent->updateactive($parent['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);

    }
}
