<?php

namespace App\Http\Controllers\ChangeRequest\Api;

use App\Factories\ChangeRequest\AttachmetsCRSFactory;
use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Change_Request\Api\attachments_CRS_Request;
use App\Http\Requests\Change_Request\Api\changeRequest_Requests;
use App\Http\Resources\ChangeRequestListResource;
use App\Http\Resources\ChangeRequestResource;
use App\Http\Resources\MyAssignmentsCRSResource;
use App\Http\Resources\MyCRSResource;
use App\Services\ChangeRequest\ChangeRequestUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class ChangeRequestController extends Controller
{
    private $changerequest;

    private $changerequeststatus;

    private $workflow;

    public function __construct(ChangeRequestFactory $changerequest, ChangeRequestStatusFactory $changerequeststatus, NewWorkFlowFactory $workflow, AttachmetsCRSFactory $attachments)
    {
        $this->changerequest = $changerequest::index();
        $this->changerequeststatus = $changerequeststatus::index();
        $this->changerworkflowequeststatus = $workflow::index();
        $this->attachments = $attachments::index();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $ChangeRequests = $this->changerequest->getAll();
        // dd($ChangeRequests);
        $ChangeRequests = ChangeRequestListResource::collection($ChangeRequests);

        return response()->json(['data' => $ChangeRequests], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        $this->changerequest->create($request->all());

        return response()->json([
            'message' => 'Created Successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(changeRequest_Requests $request)
    {
        // dd();
        $cr_id = $this->changerequest->create($request->all());

        if ($request->file()) {
            $this->attachments->add_files($request->file('filesdata'), $cr_id);
        }

        return response()->json([
            'message' => 'Created Successfully',
            'id' => $cr_id,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $cr = $this->changerequest->find($id);

        if ($cr) {
            $cr = new ChangeRequestResource($cr);
        } else {
            $cr = null;
        }
        // dd($cr);

        return response()->json(['data' => $cr], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(changeRequest_Requests $request, $id)
    {
        $this->changerequest->update($id, $request);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function update_attach(attachments_CRS_Request $request)
    {
        if ($request->file()) {
            $cr_id = $request->id;
            $this->attachments->add_files($request->file('filesdata'), $cr_id);
            // $this->attachments->update_files($request->file('filesdata'), $cr_id);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
    }

    public function search_result($id)
    {
        $cr = '39390';

        return response()->json(['data' => $cr], 200);
    }

    public function my_assignments()
    {
        $crs = $this->changerequest->my_assignments_crs();
        $my_crs = MyAssignmentsCRSResource::collection($crs);

        return response()->json(['data' => $my_crs], 200);
    }

    public function my_crs()
    {
        $crs = $this->changerequest->my_crs();
        $my_crs = MyCRSResource::collection($crs);

        return response()->json(['data' => $my_crs], 200);
    }

    /**
     * Approves a deployment for a specific Change Request.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveDeployment(Request $request)
    {

        $result = $this->changerequest->approveDeployment(
            (int) $request->cr_id,
            (int) $request->user_id
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Validates director approval for a Change Request.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function directorApproval($cr_id, Request $request)
    {
        $service = new ChangeRequestUpdateService();

        $requestData = new Request([
            'old_status_id' => $request->old_status_id,
            'new_status_id' => $request->new_status_id,
            'user_id' => $request->user_id,
        ]);

        $result = $service->update(
            (int) $cr_id,
            $requestData
        );

        return response()->json([
            'success' => true,
            'handled' => $result,
            'message' => $result ? 'Approval recorded or waiting.' : 'Approval processed or not applicable.'
        ]);
    }

    /**
     * Approves an SR business approval.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveSrBusinessApproval(Request $request)
    {
        $result = $this->changerequest->approveSrBusinessApproval(
            (int) $request->cr_id,
            (int) $request->user_id
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Rejects an SR business approval.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectSrBusinessApproval(Request $request)
    {
        $result = $this->changerequest->rejectSrBusinessApproval(
            (int) $request->cr_id,
            (int) $request->user_id
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
