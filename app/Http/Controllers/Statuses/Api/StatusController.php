<?php

namespace App\Http\Controllers\Statuses\Api;

use App\Factories\Statuses\StatusFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Statuses\Api\StatusRequest;
use App\Http\Resources\StatusResource;
use Illuminate\Foundation\Validation\ValidatesRequests;

class StatusController extends Controller
{
    use ValidatesRequests;

    private $status;

    public function __construct(StatusFactory $status)
    {

        $this->status = $status::index();

    }

    public function index()
    {
        //  = $this->status->getAll();
        $statuses = StatusResource::collection($this->status->getAll());

        return response()->json(['data' => $statuses], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(StatusRequest $request)
    {
        $this->status->create($request->all());

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
    public function update(StatusRequest $request, $id)
    {

        $status = $this->status->find($id);
        if (! $status) {
            return response()->json([
                'message' => 'status Not Exists',
            ], 422);
        }
        $this->status->update($request, $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $status = $this->status->find($id);
        if (! $status) {
            return response()->json([
                'message' => 'status Not Exists',
            ], 422);
        }
        $status = new StatusResource($status);

        return response()->json(['data' => $status], 200);
    }

    public function destroy() {}

    public function updateactive($id)
    {
        $status = $this->status->find($id);

        $this->status->updateactive($status['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);

    }
}
