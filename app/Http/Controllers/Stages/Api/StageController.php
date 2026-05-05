<?php

namespace App\Http\Controllers\Stages\Api;

use App\Factories\Stages\StageFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stages\Api\StageRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class StageController extends Controller
{
    use ValidatesRequests;

    private $stage;

    public function __construct(StageFactory $stage)
    {

        $this->stage = $stage::index();

    }

    public function index()
    {
        $stages = $this->stage->getAll();

        return response()->json(['data' => $stages], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(StageRequest $request)
    {
        $this->stage->create($request->all());

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
    public function update(StageRequest $request, $id)
    {
        $stage = $this->stage->find($id);
        if (! $stage) {
            return response()->json([
                'message' => 'stage Not Exists',
            ], 422);
        }
        $this->stage->update($request->except('_method'), $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $stage = $this->stage->find($id);
        if (! $stage) {
            return response()->json([
                'message' => 'stage Not Exists',
            ], 422);
        }

        return response()->json(['data' => $stage], 200);
    }

    public function StageStatuses($id)
    {
        $stage = $this->stage->find($id);
        if (! $stage) {
            return response()->json([
                'message' => 'stage Not Exists',
            ], 422);
        }

        return response()->json(['data' => $stage->statuses], 200);
    }

    public function destroy() {}

    public function updateactive($id)
    {
        $stage = $this->stage->find($id);

        $this->stage->updateactive($stage['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);

    }
}
