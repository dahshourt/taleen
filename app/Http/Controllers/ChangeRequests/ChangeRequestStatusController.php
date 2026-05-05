<?php

namespace App\Http\Controllers\ChangeRequest;

use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\Workflow\WorkflowFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ChangeRequestStatusController extends Controller
{
    private $changerequest;

    private $changerequeststatus;

    private $workflow;

    public function __construct(ChangeRequestFactory $changerequest, ChangeRequestStatusFactory $changerequeststatus, WorkflowFactory $workflow)
    {

        $this->changerequest = $changerequest::index();
        $this->changerequeststatus = $changerequeststatus::index();
        $this->changerworkflowequeststatus = $workflow::index();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $group = $request->header('group');
        //

        $CRs = $this->changerequeststatus->getAll($group);

        return response()->json(['data' => $CRs], 200);
        //    return $CRs;

    }

    public function list_crs()
    {

        $crs = $this->changerequeststatus->list_crs();

        return response()->json(['data' => $crs], 200);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
