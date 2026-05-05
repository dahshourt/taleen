<?php

namespace App\Http\Controllers\Statuses;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Http\Requests\Statuses\StatusRequest;
use App\Factories\Statuses\StatusFactory;
use App\Http\Resources\StatusResource;
use App\Http\Repository\Stages\StageRepository;
use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Workflow\Workflow_type_repository;
use App\Exports\StatusesExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StatusController extends Controller
{
    
    use ValidatesRequests;
    private $status;

    function __construct(StatusFactory $status){
        // $this->middleware(function($request,$next){
        //     dd($request);
        // });

        $this->status = $status::index();
        $this->view = 'statuses';
        $view = 'statuses';
        $route = 'statuses';
        $OtherRoute = 'status';

        $title = 'Statuses';
        $form_title = 'Status';
        view()->share(compact('view','route','title','form_title','OtherRoute'));

    }

    public function index()
    {
        $this->authorize('List Statuses'); // permission check
        //  = $this->status->getAll();
        $collection = StatusResource::collection($this->status->paginateAll(['setByGroupStatuses.group', 'viewByGroupStatuses.group', 'stage:id,name']));
        return view("$this->view.index",compact('collection'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create Status'); // permission check
        //
        $stages = (new StageRepository)->getAll();
        $groups = (new GroupRepository)->getAll();
		$types = (new Workflow_type_repository)->get_workflow_all_subtype();
        return view("$this->view.create",compact('stages','groups','types'));
    }
    /**
     * Send or resend the verification code.
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Illuminate\Validation\ValidationException
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StatusRequest $request)
    {
        $this->authorize('Create Status'); // permission check
        $this->status->create($request->all());

        return redirect()->back()->with('status' , 'Created Successfully' );

    }
    public function edit($id)
    {
        $this->authorize('Edit Status'); // permission check
        $row = $this->status->find($id);
        $stages = (new StageRepository)->getAll();
        $groups = (new GroupRepository)->getAll();
        $set_group_ids = $row->group_statuses->where('type', 1)->pluck('group_id')->toArray();
        $view_group_ids = $row->group_statuses->where('type', 2)->pluck('group_id')->toArray();
		
		$types = (new Workflow_type_repository)->get_workflow_all_subtype();
        return view("$this->view.edit",compact('row','stages','groups' , 'set_group_ids', 'view_group_ids','types'));

    }

    /**
     * Send or resend the verification code.
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Illuminate\Validation\ValidationException
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StatusRequest $request, $id)
    {
        $this->authorize('Edit Status'); // permission check

        $status = $this->status->find($id);

        if(!$status)
        {
            return redirect()->back()->with('status' , 'status Not Exists' );

        }
        $this->status->update($request,$id);
        //$this->updateactive($id);
        return redirect()->back()->with('status' , 'Updated Successfully' );

    }

    public function show($id)
    {
        $this->authorize('Show Status'); // permission check
        $status = $this->status->find($id);
        if(!$status)
        {
            return redirect()->back()->with('status' , 'status Not Exists' );
        }
        $status = new StatusResource($status);
        return redirect()->back()->with('status' , 'Updated Successfully' );
    }

    public function destroy()
    {
        $this->authorize('Delete Status'); // permission check

    }
    public function updateactive(Request $request){

        $this->authorize('Active Status'); // permission check

        $id=$request->id;
		   $status = $this->status->find($id);

		   $this->status->updateactive($status['active'],$id);


		    return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success'
        ]);



	}

    public function export(): BinaryFileResponse
    {
        $this->authorize('List Statuses');

        return Excel::download(new StatusesExport, 'statuses_' . date('Y-m-d_H-i-s') . '.xlsx');
    }

}
