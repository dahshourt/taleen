<?php

namespace App\Http\Controllers\Workflow\Api;

use App\Factories\Workflow\WorkflowDependFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\Api\DependWorkFlowRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class DependWorkflowController extends Controller
{
    use ValidatesRequests;

    private $workflow_depend;

    public function __construct(WorkflowDependFactory $workflow_depend)
    {

        $this->workflow_depend = $workflow_depend::index();

    }

    public function index()
    {
        $workflow_depend = $this->workflow_depend->getAll();

        return response()->json(['data' => $workflow_depend], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(DependWorkFlowRequest $request)
    {

        $this->workflow_depend->create($request->all());

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
    public function update(DependWorkFlowRequest $request, $id)
    {

        $workflow_depend = $this->workflow_depend->find($id);
        if (! $workflow_depend) {
            return response()->json([
                'message' => 'Depend Workflow Not Exists',
            ], 422);
        }
        $this->workflow_depend->update($request->except('_method'), $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $workflow_depend = $this->workflow_depend->find($id);
        if (! $workflow_depend) {
            return response()->json([
                'message' => 'depend-Workflow Not Exists',
            ], 422);
        }

        return response()->json(['data' => $workflow_depend], 200);
    }

    public function destroy($id)
    {

        $workflow_depend = $this->workflow_depend->find($id);
        if (! $workflow_depend) {
            return response()->json([
                'message' => 'Depend Workflow Not Exists',
            ], 422);
        }
        $this->workflow_depend->delete($id);

        return response()->json([
            'message' => 'Deleted Successfully',
        ]);
    }
}
