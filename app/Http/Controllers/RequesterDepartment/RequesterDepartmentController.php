<?php

namespace App\Http\Controllers\RequesterDepartment;

use App\Factories\RequesterDepartment\RequesterDepartmentFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequesterDepartment\RequesterDepartmentRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class RequesterDepartmentController extends Controller
{
    use ValidatesRequests;

    private $requesterDepartment;
    private $view;

    public function __construct(RequesterDepartmentFactory $requesterDepartment)
    {
        $this->requesterDepartment = $requesterDepartment::index();
        $this->view = 'requester_departments';
        $view = 'requester_departments';
        $route = 'requester-department';
        $OtherRoute = 'requester-department';

        $title = 'Requester Departments';
        $form_title = 'Requester Department';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    public function index()
    {
        $this->authorize('List Requester Departments'); // permission check

        $collection = $this->requesterDepartment->paginateAll();

        return view("$this->view.index", compact('collection'));
    }

    public function create()
    {
       $this->authorize('Create Requester Department'); // permission check
        return view("$this->view.create");
    }

    public function store(RequesterDepartmentRequest $request)
    {
        $this->authorize('Create Requester Department'); // permission check
        $this->requesterDepartment->create($request->all());

        return redirect()->route('requester-department.index')->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $this->authorize('Edit Requester Department'); // permission check
        $row = $this->requesterDepartment->find($id);

        return view("$this->view.edit", compact('row'));
    }

    public function update(RequesterDepartmentRequest $request, $id)
    {
        $this->authorize('Edit Requester Department'); // permission check

        $this->requesterDepartment->update($request->except(['_token', '_method']), $id);

        return redirect()->route('requester-department.index')->with('status', 'Updated Successfully');
    }

    public function show($id)
    {
        $this->authorize('Show Requester Department'); // permission check
        $requesterDepartment = $this->requesterDepartment->find($id);
        
        if (!$requesterDepartment) {
            return response()->json([
                'message' => 'Requester Department Not Found',
            ], 404);
        }

        return response()->json(['data' => $requesterDepartment], 200);
    }

    public function destroy($id)
    {
        $this->authorize('Delete Requester Department'); // permission check
        $this->requesterDepartment->delete($id);
        
        return response()->json([
            'message' => 'Deleted Successfully',
            'status' => 'success',
        ]);
    }

    public function updateactive(Request $request)
    {
        $this->authorize('Active Requester Department'); // permission check

        $data = $this->requesterDepartment->find($request->id);
        $this->requesterDepartment->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    }
}
