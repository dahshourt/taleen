<?php

namespace App\Http\Controllers\Workflow;

use App\Factories\Workflow\WorkflowFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Statuses\StatusRepository;
use App\Http\Repository\Statuses\StatusWorkFlowRepository;
use App\Http\Repository\Workflow\Workflow_type_repository;
use App\Http\Repository\Workflow\WorkflowRepository;
use App\Http\Requests\Workflow\WorkflowRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class WorkflowController extends Controller
{
    // use ValidatesRequests;
    private $workflow;

    public function __construct(WorkflowFactory $workflow)
    {

        $this->workflow = $workflow::index();
        $this->view = 'workflows';
        $view = 'workflows';
        $route = 'workflows';
        $OtherRoute = 'workflow2';

        $title = 'workflows';
        $form_title = 'workflow';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {

        $collection = $this->workflow->paginateAll();

        return view("$this->view.index", compact('collection'));
    }

    public function create()
    {

        $Workflows = (new WorkflowRepository)->getAll();
        $types = (new Workflow_type_repository)->get_workflow_all_subtype();
        $groups = (new GroupRepository)->getAll();
        $statuses = (new StatusRepository)->getAll();

        return view("$this->view.create", compact('Workflows', 'types', 'groups', 'statuses'));
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

        $this->workflow->create($request->all());

        return redirect()->back()->with('workflow', 'Created Successfully');
    }

    public function edit($id)
    {
        $row = $this->workflow->find($id);
        $types = (new Workflow_type_repository)->get_workflow_all_subtype();
        $Workflows = (new StatusWorkFlowRepository)->getAll();
        $statuses = (new StatusRepository)->getAll();

        return view("$this->view.edit", compact('row', 'Workflows', 'types', 'statuses'));

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
        $Workflow = $this->workflow->find($id);
        if (! $Workflow) {
            return redirect()->back()->with('Workflow', 'status Not Exists');
        }
        $this->workflow->update($request->except('_method', '_token', 'type_id', 'to_status_lable'), $id);

        return redirect()->back()->with('Workflow', 'Updated Successfully');
    }

    public function show($id)
    {
        $Workflow = $this->workflow->find($id);
        if (! $Workflow) {
            return redirect()->back()->with('Workflow', 'status Not Exists');
        }

        return redirect()->back()->with('Workflow', 'Updated Successfully');
    }

    public function WorkflowStatuses($id)
    {
        $Workflow = $this->workflow->find($id);
        if (! $Workflow) {
            return redirect()->back()->with('Workflow', 'status Not Exists');
        }

        return redirect()->back()->with('Workflow', 'Updated Successfully');
    }

    public function destroy() {}

    public function listFromStatuses($id)
    {
        $Workflow = $this->workflow->listFromStatuses($id);
        if (! $Workflow) {
            return redirect()->back()->with('Workflow', 'status Not Exists');
        }

        return redirect()->back()->with('Workflow', 'Updated Successfully');
    }
}
