<?php

namespace App\Http\Controllers\Statuses\Api;

use App\Factories\Statuses\StatusWorkFlowFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Statuses\Api\StatusWorkFlowRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class StatusWorkFlowController extends Controller
{
    use ValidatesRequests;

    private $status_work_flow;

    public function __construct(StatusWorkFlowFactory $status_work_flow)
    {

        $this->status_work_flow = $status_work_flow::index();

    }

    public function index()
    {
        $status_work_flow = $this->status_work_flow->getAll();

        return response()->json(['data' => $status_work_flow], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(StatusWorkFlowRequest $request)
    {
        $this->status_work_flow->create($request->all());

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
    public function update(StatusWorkFlowRequest $request, $id)
    {
        $status_work_flow = $this->status_work_flow->find($id);
        if (! $status_work_flow) {
            return response()->json([
                'message' => 'status work flow Not Exists',
            ], 422);
        }
        $this->status_work_flow->update($request->except('_method'), $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $status_work_flow = $this->status_work_flow->find($id);
        if (! $status_work_flow) {
            return response()->json([
                'message' => 'status work flow Not Exists',
            ], 422);
        }

        return response()->json(['data' => $status_work_flow], 200);
    }

    public function destroy() {}
}
