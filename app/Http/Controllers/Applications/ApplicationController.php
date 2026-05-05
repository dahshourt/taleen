<?php

namespace App\Http\Controllers\Applications;

use App\Factories\Applications\ApplicationFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Workflow\Workflow_type_repository;
use App\Http\Requests\Applcations\ApplicationsRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    use ValidatesRequests;

    private $Application;

    public function __construct(ApplicationFactory $Application)
    {

        $this->Application = $Application::index();
        $this->view = 'applications';
        $view = 'applications';
        $route = 'applications';
        $OtherRoute = 'application';

        $title = 'Applications';
        $form_title = 'Application';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {
        $this->authorize('List Applications'); // permission check
        $collection = $this->Application->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create Application');
        $workflow = new Workflow_type_repository();
        $workflow_subtype = $workflow->get_workflow_all_subtype();
        $parent_id = null;
        $parent_apps = $this->Application->getAllWithFilter($parent_id);
        $users = \App\Models\User::active()->get();

        return view("$this->view.create", compact('workflow_subtype', 'parent_apps', 'users'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(ApplicationsRequest $request)
    {
        $this->Application->create($request->all());

        return redirect()->back()->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $this->authorize('Edit Application');
        $row = $this->Application->find($id);
        $workflow = new Workflow_type_repository();
        $workflow_subtype = $workflow->get_workflow_all_subtype();
        $parent_id = null;
        $parent_apps = $this->Application->getAllWithFilter($parent_id);
        $users = \App\Models\User::active()->get();

        return view("$this->view.edit", compact('row', 'workflow_subtype', 'parent_apps', 'users'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(ApplicationsRequest $request, $id)
    {
        $this->Application->update($request->except(['_token', '_method']), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
    }

    public function destroy()
    {
        $this->authorize('Delete Cab User'); // permission check

    }

    public function updateactive(Request $request)
    {
        $data = $this->Application->find($request->id);
        $this->Application->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    } // end method

    public function download($id)
    {
        $file = $this->Application->find($id);
        $filePath = public_path('uploads/' . $file->file); // in config
        // dd($filePath);
        if (file_exists($filePath)) {
            return response()->download($filePath, $file->file);
        }

        return redirect()->back()->withErrors('File not found.');
    }
}
