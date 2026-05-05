<?php

namespace App\Http\Controllers\Workflow;

use App\Factories\Workflow\NewWorkFlowFactory;
use App\Factories\Workflow\WorkflowFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Statuses\StatusRepository;
use App\Http\Repository\Workflow\Workflow_type_repository;
use App\Http\Requests\Workflow\Api\NewWorkflowRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class NewWorkFlowController extends Controller
{
    use ValidatesRequests;

    private $Workflow;

    public function __construct(NewWorkFlowFactory $NewWorkFlowFactory, WorkflowFactory $WorkflowFactory)
    {

        $this->Workflow = $WorkflowFactory::index();
        $this->NewWorkflow = $NewWorkFlowFactory::index();
        $this->view = 'workflows';
        $view = 'workflows';
        $route = 'NewWorkFlowController';
        $OtherRoute = 'workflow2';
        $title = 'Workflows';
        $form_title = 'Workflow';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {
        $this->authorize('List Workflows'); // permission check

        $collection = $this->NewWorkflow->ListAllWorkflowWithoutRelease();

        return view("$this->view.index", compact('collection'));
    }

    public function ListAllWorkflows()
    {
        $this->authorize('List Workflows'); // permission check

        $collection = $this->NewWorkflow->paginateAll();
        $types = (new Workflow_type_repository)->get_workflow_all_subtype();

        return view("$this->view.index", compact('collection', 'types'));
    }

    public function create()
    {
        $this->authorize('Create Workflow'); // permission check

        $Workflows = $this->Workflow->getAll();
        $types = (new Workflow_type_repository)->get_workflow_all_subtype();
        $groups = (new GroupRepository)->getAll();
        $statuses = (new StatusRepository)->getAll();

        return view("$this->view.create", compact('Workflows', 'types', 'groups', 'statuses'));
    }

    public function store(NewWorkflowRequest $request)
    {
        $this->authorize('Create Workflow'); // permission check
        $res = $this->NewWorkflow->create($request->all());

        return redirect()->back()->with('success', 'Created Successfully');
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(NewWorkflowRequest $request, $id)
    {
        $this->authorize('Edit Workflow'); // permission check

        $Workflow = $this->NewWorkflow->find($id);
        if (! $Workflow) {
            return response()->json([
                'message' => 'Workflow Not Exists',
            ], 422);
        }

        $collection = $this->NewWorkflow->update($request, $id);

        return $this->index();
    }

    public function show($id)
    {
        $this->authorize('Show Workflow'); // permission check
        $row = $this->NewWorkflow->find($id);
        /*echo "<pre>";
        print_r($find_workflow);
        echo "</pre>";*/
        // dd($row);
        $Workflows = $this->Workflow->getAll();
        $types = (new Workflow_type_repository)->get_workflow_all_subtype();
        $groups = (new GroupRepository)->getAll();
        $statuses = (new StatusRepository)->getAll();

        return view("$this->view.edit", compact('Workflows', 'types', 'groups', 'statuses', 'row'));
    }

    public function WorkflowStatuses($id)
    {
        $Workflow = $this->Workflow->find($id);
        if (! $Workflow) {
            return response()->json([
                'message' => 'Workflow Not Exists',
            ], 422);
        }

        return response()->json(['data' => $Workflow->statuses], 200);
    }

    public function destroy()
    {
        $this->authorize('Delete Workflow'); // permission check

    }

    public function listFromStatuses($id)
    {
        $Workflow = $this->Workflow->listFromStatuses($id);
        if (! $Workflow) {
            return response()->json([
                'message' => 'Workflow Not Exists',
            ], 422);
        }

        return response()->json(['data' => $Workflow], 200);
    }

    public function updateactive(Request $request)
    {

        $this->authorize('Active Workflow'); // permission check
        $id = $request->id;

        $data = $this->NewWorkflow->find($id);

        $this->NewWorkflow->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);

    }

    public function SameFromWorkflow(Request $request)
    {
        $this->authorize('Edit Workflow'); // permission check
        if ($request->same_time_from) {
            $statuses = $this->NewWorkflow->ListTypeWorkflow($request->type_id);
        } else {
            $statuses = (new StatusRepository)->getAll();
        }
        $same_time_from = $request->same_time_from;

        return view("$this->view.from_previous", compact('same_time_from', 'statuses'));
    }

    public function exportWorkflows()
    {
        $this->authorize('List Workflows'); // permission check

        $filename = 'workflows_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

        return Excel::download(new \App\Exports\WorkflowsExport(), $filename);
    }
}
