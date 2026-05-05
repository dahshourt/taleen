<?php

namespace App\Http\Controllers\CustomFields;

use App\Factories\CustomField\CustomFieldFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Groups\GroupRepository;
use App\Http\Repository\Statuses\StatusRepository;
use App\Http\Repository\Workflow\Workflow_type_repository;
use App\Http\Requests\CustomFields\Api\CustomFieldRequest; // StatusRepository
use Auth; //
use Illuminate\Foundation\Validation\ValidatesRequests;

class CustomFieldController extends Controller
{
    use ValidatesRequests;

    private $CustomField;

    public function __construct(CustomFieldFactory $CustomField)
    {
        // Ensure the user is authenticated
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            if (! $this->user->hasRole('Super Admin') && ! $this->user->can('Access CustomFields')) {
                abort(403, 'This action is unauthorized.');
            } else {
                return $next($request);
            }
        });

        $title = 'Custom Fields';
        $form_title = 'Custom Field';
        $route = 'custom_fields.store'; // Adjust this according to your route
        $view = 'custom_fields';
        $this->CustomField = $CustomField::index();
        view()->share(compact('view', 'route', 'title', 'form_title'));
    }

    public function create()
    {
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $wf_type_name = $get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.create', compact('wf_type_name', 'validation_type_name', 'custom_fields'));
    }

    // createCF
    public function createCF()
    {
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $wf_type_name = $get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.createCF', compact('wf_type_name', 'validation_type_name', 'custom_fields'));
    }

    // createCF
    public function view()
    {
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $wf_type_name = $get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.view', compact('wf_type_name', 'validation_type_name', 'custom_fields'));
    }

    // viewupdate
    public function viewCF()
    {
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $wf_type_name = $get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.viewCF', compact('wf_type_name', 'validation_type_name', 'custom_fields'));
    }

    public function viewupdate()
    {
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $wf_type_name = $get_workflow_subtype;

        return view('custom_fields.viewupdate', compact('wf_type_name'));
    }// viewupdate

    public function search()
    {
        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;

        $groups = new GroupRepository();
        $groups = $groups->getAllWithFilter($parent_id);

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        // $wf_type_name =$get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.search', compact('groups', 'validation_type_name', 'custom_fields'));
    }

    public function special()
    {

        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;
        //  echo   $parent_id ; die;
        $groups = new GroupRepository();
        $groups = $groups->getAllWithFilter($parent_id);
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $workflowTypes = $get_workflow_subtype;

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        // $wf_type_name =$get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.search_special', compact('groups', 'validation_type_name', 'workflowTypes'));

    }

    public function specialviewresult()
    {

        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;
        //  echo   $parent_id ; die;
        $groups = new GroupRepository();
        $groups = $groups->getAllWithFilter($parent_id);
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $workflowTypes = $get_workflow_subtype;

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        // $wf_type_name =$get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.view_result_special', compact('groups', 'validation_type_name', 'workflowTypes'));

    }

    // sp
    public function specialviewadvanced()
    {

        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;
        //  echo   $parent_id ; die;
        $groups = new GroupRepository();
        $groups = $groups->getAllWithFilter($parent_id);
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $workflowTypes = $get_workflow_subtype;

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        // $wf_type_name =$get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.view_special_search_advanced', compact('groups', 'validation_type_name', 'workflowTypes'));

    }

    public function specialview()
    {

        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;
        //  echo   $parent_id ; die;
        $groups = new GroupRepository();
        $groups = $groups->getAllWithFilter($parent_id);
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $workflowTypes = $get_workflow_subtype;

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        // $wf_type_name =$get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.view_special', compact('groups', 'validation_type_name', 'workflowTypes'));

    }

    // specialviewsearch
    public function specialviewsearch()
    {

        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;
        //  echo   $parent_id ; die;
        $groups = new GroupRepository();
        $groups = $groups->getAllWithFilter($parent_id);
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $workflowTypes = $get_workflow_subtype;

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        // $wf_type_name =$get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.view_special_search', compact('groups', 'validation_type_name', 'workflowTypes'));

    }

    // specialviewsearch
    public function specialviewupdate()
    {

        $parent_id = isset(request()->route()->getAction()['parent']) ? request()->route()->getAction()['parent'] : false;
        //  echo   $parent_id ; die;
        $statuses = new StatusRepository();
        $statuses = $statuses->getAll();
        $workflow = new Workflow_type_repository();
        $get_workflow_subtype = $workflow->get_workflow_all_subtype();

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        $workflowTypes = $get_workflow_subtype;

        // Fetch workflow types and validation types (replace with actual data fetching logic)
        // $wf_type_name =$get_workflow_subtype;
        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
        ];

        // Fetch custom fields (replace with actual data fetching logic)
        $custom_fields = [
            ['id' => 1, 'label' => 'CR status'],
            ['id' => 2, 'label' => 'Release CR'],
            ['id' => 3, 'label' => 'Assigned Unit'],
            ['id' => 4, 'label' => 'Testable'],
        ];

        return view('custom_fields.view_special_update', compact('statuses', 'validation_type_name', 'workflowTypes'));

    }

    // specialviewupdate
    public function loadCustomFields(Request $request)
    {
        $wf_type_id = $request->input('wf_type_id');
        // Fetch custom fields based on wf_type_id
        $custom_fields = CustomField::where('wf_type_id', $wf_type_id)->get();

        $validation_type_name = [
            ['value' => 1, 'text' => 'Validation 1'],
            ['value' => 2, 'text' => 'Validation 2'],
            // Add more validation types as needed
        ];

        return view('custom_fields.form', compact('custom_fields', 'validation_type_name'))->render();
    }

    public function index()
    {
        $CustomFields = $this->CustomField->getAll();

        return response()->json(['data' => $CustomFields], 200);
    }

    public function store(CustomFieldRequest $request)
    {

        $this->CustomField->create($request->all());

        return response()->json([
            'message' => 'Created Successfully',
        ]);
    }

    public function update(CustomFieldRequest $request, $id)
    {
        $CustomField = $this->CustomField->find($id);
        if (! $CustomField) {
            return response()->json([
                'message' => 'Group Not Exists',
            ], 422);
        }
        $this->CustomField->update($request, $id);

        return response()->json([
            'message' => 'Updated Successfully',
        ]);
    }

    public function show($id)
    {
        $CustomField = $this->CustomField->find($id);

        return response()->json(['data' => $CustomField], 200);
    }
}
