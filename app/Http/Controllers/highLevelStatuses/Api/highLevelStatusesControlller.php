<?php

namespace App\Http\Controllers\highLevelStatuses\Api;

use App\Factories\HighLevelStatuses\HighLevelStatusesFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\HighLevelStatuses\highlevelrequest;
use App\Http\Resources\HighLevelStatusResource;
use Illuminate\Foundation\Validation\ValidatesRequests;

class highLevelStatusesControlller extends Controller
{
    use ValidatesRequests;

    private $highLevelStatuses;

    public function __construct(HighLevelStatusesFactory $highLevelStatuses)
    {

        $this->highLevelStatuses = $highLevelStatuses::index();

    }

    public function index()
    {

        //  = $this->status->getAll();
        $highLevelStatuses = HighLevelStatusResource::collection($this->highLevelStatuses->getAll());

        return response()->json(['data' => $highLevelStatuses], 200);
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(highlevelrequest $request)
    {

        $this->highLevelStatuses->create($request->all());

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
    public function update(highlevelrequest $request, $id)
    {

        $highLevelStatuses = $this->highLevelStatuses->find($id);
        if (! $highLevelStatuses) {
            return response()->json([
                'message' => 'status Not Exists',
            ], 422);
        }
        $this->highLevelStatuses->update($request, $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $highLevelStatuses = $this->highLevelStatuses->find($id);
        if (! $highLevelStatuses) {
            return response()->json([
                'message' => 'status Not Exists',
            ], 422);
        }
        $highLevelStatuses = new HighLevelStatusResource($highLevelStatuses);

        return response()->json(['data' => $highLevelStatuses], 200);
    }

    public function destroy() {}

    public function updateactive($id)
    {
        $highLevelStatuses = $this->highLevelStatuses->find($id);

        $this->highLevelStatuses->updateactive($highLevelStatuses['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);

    }
}
