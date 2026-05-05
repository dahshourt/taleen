<?php

namespace App\Http\Controllers\Systems\Api;

use App\Factories\Systems\SystemFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Systems\Api\SystemRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class SystemController extends Controller
{
    use ValidatesRequests;

    private $system;

    public function __construct(SystemFactory $system)
    {

        $this->system = $system::index();

    }

    public function index()
    {
        $systems = $this->system->getAll();

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
    public function store(SystemRequest $request)
    {

        $this->system->create($request->all());

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
    public function update(SystemRequest $request, $id)
    {
        $system = $this->system->find($id);
        if (! $system) {
            return response()->json([
                'message' => 'system Not Exists',
            ], 422);
        }
        $this->system->update($request->except('_method'), $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $system = $this->system->find($id);
        if (! $system) {
            return response()->json([
                'message' => 'system Not Exists',
            ], 422);
        }

        return response()->json(['data' => $system], 200);
    }

    public function StageStatuses($id)
    {
        $system = $this->system->find($id);
        if (! $system) {
            return response()->json([
                'message' => 'system Not Exists',
            ], 422);
        }

        return response()->json(['data' => $system->statuses], 200);
    }

    public function destroy() {}

    public function updateactive($id)
    {
        $system = $this->system->find($id);

        $this->system->updateactive($system['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);

    }
}
