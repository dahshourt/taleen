<?php

namespace App\Http\Controllers\Workflow\Api;

use App\Factories\Workflow\WorkflowFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\Api\WorkflowRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class WorkflowController extends Controller
{
    use ValidatesRequests;

    private $Workflow;

    public function __construct(WorkflowFactory $Workflow)
    {

        $this->Workflow = $Workflow::index();

    }

    public function index()
    {
        $Workflows = $this->Workflow->getAll();

        return response()->json(['data' => $Workflows], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(WorkflowRequest $request)
    {

        $this->Workflow->create($request->all());

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
    public function update(WorkflowRequest $request, $id)
    {
        $Workflow = $this->Workflow->find($id);
        if (! $Workflow) {
            return response()->json([
                'message' => 'Workflow Not Exists',
            ], 422);
        }
        $this->Workflow->update($request->except('_method'), $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $Workflow = $this->Workflow->find($id);
        if (! $Workflow) {
            return response()->json([
                'message' => 'Workflow Not Exists',
            ], 422);
        }

        return response()->json(['data' => $Workflow], 200);
    }

    public function WorkflowStatuses($id)
    {
        $Workflow = $this->Workflow->find($id);
        if (! $Workflow) {
            return response()->json([
                'message' => 'Workflow Not Exists',
            ], 422);
        }

        return response()->json(['data' => $Workflow->statuses], 200);
    }

    public function destroy() {}

    public function listFromStatuses($id)
    {
        $Workflow = $this->Workflow->listFromStatuses($id);
        if (! $Workflow) {
            return response()->json([
                'message' => 'Workflow Not Exists',
            ], 422);
        }

        return response()->json(['data' => $Workflow], 200);
    }
}
