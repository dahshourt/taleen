<?php
namespace App\Http\Controllers\ChangeRequest;
use App\Factories\Applications\ApplicationFactory;
use App\Factories\ChangeRequest\AttachmetsCRSFactory;
use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\CustomField\CustomFieldGroupTypeFactory;
use App\Factories\Defect\DefectFactory;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Factories\Workflow\Workflow_type_factory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Workflow\Workflow_type_repository;
use App\Http\Requests\Change_Request\Api\attachments_CRS_Request;
use App\Http\Requests\Change_Request\Api\changeRequest_Requests;
use App\Http\Resources\MyCRSResource;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\Group;
use App\Models\NewWorkFlow;
use App\Models\WorkFlowType;
use App\Services\ChangeRequest\ChangeRequestAttachmentService;
use App\Services\ChangeRequest\ChangeRequestService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;

class ChangeRequestController extends Controller
{
    private const FORM_TYPE_CREATE = 1;
    private const FORM_TYPE_EDIT = 2;
    private const FORM_TYPE_VIEW = 3;

    private $changerequest;
    private $changerequeststatus;
    private $workflow;
    private $workflow_type;
    private $attachments;
    private $custom_field_group_type;
    private $applications;
    private $defects;
    private $view = 'change_request';
    private $changeRequestService;
    private $attachmentService;

    public function __construct(
        DefectFactory $defect,
        ChangeRequestFactory $changerequest,
        ChangeRequestStatusFactory $changerequeststatus,
        NewWorkFlowFactory $workflow,
        AttachmetsCRSFactory $attachments,
        Workflow_type_factory $workflow_type,
        CustomFieldGroupTypeFactory $custom_field_group_type,
        ApplicationFactory $applications,
        ChangeRequestService $changeRequestService,
        ChangeRequestAttachmentService $attachmentService
    ) {
        $this->changerequest = $changerequest::index();
        $this->defects = $defect::index();
        $this->changerequeststatus = $changerequeststatus::index();
        $this->workflow = $workflow::index();
        $this->workflow_type = $workflow_type::index();
        $this->attachments = $attachments::index();
        $this->custom_field_group_type = $custom_field_group_type::index();
        $this->applications = $applications::index();
        $this->changeRequestService = $changeRequestService;
        $this->attachmentService = $attachmentService;

        $this->shareViewData();
    }

    public function index()
    {
        try {
            $this->authorize('List change requests');

            $active_work_flows = app(Workflow_type_repository::class)->getWorkflowsForListCRs();
            $active_workflows_type_ids = $active_work_flows->pluck('id')->toArray();

            $user_groups = auth()->user()->groups()->select(['groups.id', 'groups.title'])->get();
            $user_groups_ids = $user_groups->pluck('id');
            if (count($user_groups_ids) > 1) {
                $user_groups_ids = $user_groups_ids->prepend($user_groups_ids->toArray());
            }


            $crs_by_user_groups_by_workflow = [];
            foreach ($user_groups_ids as $user_group_id) {
                $key = $user_group_id;

                if (is_array($user_group_id)) {
                    $key = 'all';
                }

                $crs_by_user_groups_by_workflow[$key] = $this->changerequest->getAllForLisCRs($active_workflows_type_ids, $user_group_id);
            }


            // Preload all workflow data to prevent N+1 queries
            $this->preloadWorkflowData($active_workflows_type_ids);

            return view("{$this->view}.index", compact('crs_by_user_groups_by_workflow', 'active_work_flows', 'user_groups'));
        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to change requests list', [
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    public function dvision_manager_cr()
    {
        try {
            $this->authorize('CR Waiting Approval');

            $title = 'CR Waiting Approval';
            $collection = $this->changerequest->dvision_manager_cr();

            return view("{$this->view}.dvision_manager_cr", compact('collection', 'title'));
        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to division manager CRs', [
                'user_id' => auth()->id(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    public function cr_pending_cap()
    {
        try {
            $this->authorize('Show cr pending cap');

            $title = 'CR Pending Cap';
            $collection = $this->changerequest->cr_pending_cap();

            return view("{$this->view}.cr_pending_cap", compact('collection', 'title'));
        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to division manager CRs', [
                'user_id' => auth()->id(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    public function selectGroup(int $groupId)
    {
        $group = Group::find($groupId);

        if (!$group) {
            return redirect()->back()->with('error', 'Group not found.');
        }

        session([
            'default_group' => $groupId,
            'default_group_name' => $group->title,
        ]);

        return redirect()->back()->with('success', 'Group selected successfully.');
    }

    // Renamed from asd
    public function selectUserGroup(?int $group = null)
    {
        if (!$group) {
            return redirect()->back()->with('error', 'No group provided.');
        }

        $selectedGroup = Group::find($group);
        if (!$selectedGroup) {
            return redirect()->back()->with('error', 'Group not found.');
        }

        // Validate user access to group
        $userGroups = auth()->user()->user_groups()->with('group')->get();
        $hasAccess = $userGroups->pluck('group.id')->contains($group);

        if (!$hasAccess) {
            Log::warning('User attempted to access unauthorized group', [
                'user_id' => auth()->id(),
                'group_id' => $group,
            ]);

            return redirect()->back()->with('error', 'You do not have access to this group.');
        }

        session([
            'default_group' => $group,
            'current_group' => $group,
            'default_group_name' => $selectedGroup->title,
            'current_group_name' => $selectedGroup->title,
        ]);

        return redirect()->back()->with('success', 'Group selected successfully.');
    }

    public function Allsubtype()
    {
        $this->authorize('Create ChangeRequest');
        $target_systems = $this->applications->getAllActive();

        return view("{$this->view}.list_work_flow", compact('target_systems'));
    }

    public function create()
    {
        $this->authorize('Create ChangeRequest');

        $target_system_id = request()->get('target_system_id');
        if (!$target_system_id) {
            return redirect()->back()->with('error', 'Target system ID is required.');
        }

        $target_system = $this->applications->find($target_system_id);
        if (!$target_system) {
            return redirect()->back()->with('error', 'Target system not found.');
        }

        $workflow_type_id = $this->applications->workflowType($target_system_id)->id;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowType(
            $workflow_type_id,
            self::FORM_TYPE_CREATE
        );
        $title = (!empty($workflow_type_id) && $workflow_type_id == 9)
            ? "Create {$target_system->name} - Promo"
            : "Create {$target_system->name} - CR";

        $approval_level = '';
        $approved_by = '';

        if ($workflow_type_id == 15) {
            $approval = \App\Models\SrRequesterApproval::where('requester_id', auth()->id())
                ->where('active', 1)
                ->with('approvalUsers')
                ->first();

            if ($approval) {
                $approval_level = $approval->approval_level;
                $approved_by = $approval->approvalUsers->pluck('user_name')->implode(', ');
            }
        }

        $biMappedFieldsNames = [];
        if (in_array($workflow_type_id, [16, 17])) {
            $allMappedFieldIds = \App\Models\BiRequestTypeField::distinct()->pluck('custom_field_id');
            $biMappedFieldsNames = \App\Models\CustomField::whereIn('id', $allMappedFieldIds)->pluck('name')->toArray();
        }

        return view("{$this->view}.create", compact(
            'CustomFields',
            'workflow_type_id',
            'target_system',
            'title',
            'approval_level',
            'approved_by',
            'biMappedFieldsNames'
        ));
    }

    public function store(changeRequest_Requests $request)
    {
        $this->authorize('Create ChangeRequest');

        if ($request->get('workflow_type_id') == 15) {
            $approval = \App\Models\SrRequesterApproval::where('requester_id', auth()->id())
                ->where('active', 1)
                ->with('approvalUsers')
                ->first();

            if ($approval) {
                $request->merge([
                    'approval_level' => $approval->approval_level,
                    'approved_by' => $approval->approvalUsers->pluck('user_name')->implode(', ')
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $this->attachmentService->validateAttachments($request);

            $cr_data = $this->changeRequestService->createChangeRequest($request->all());
            $cr_id = $cr_data['id'];
            $cr_no = $cr_data['cr_no'];

            $this->attachmentService->handleFileUploads($request, $cr_id);

            DB::commit();

            Log::info('Change request created successfully', [
                'cr_id' => $cr_id,
                'cr_no' => $cr_no,
                'user_id' => auth()->id(),
            ]);
            session()->flash('cr_id', $cr_id);

            return redirect()->back()->with('status', "Created Successfully CR#{$cr_no}");

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create change request', [
                'error' => $e,
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to create change request. Please try again.');
        }
    }

    public function show(int $id)
    {
        $this->authorize('Show ChangeRequest');

        $cr = $this->changerequest->findById($id);

        if (!$cr) {
            return redirect()->back()->with('error', 'Change request not found.');
        }

        $viewData = $this->changeRequestService->prepareViewData($cr, $id);

        return view("{$this->view}.show", $viewData);

    }

    public function edit_cab(int $id)
    {
        return $this->edit($id, true);
    }

    public function edit(int $id, bool $cab_cr_flag = false)
    {
        $this->authorize('Edit ChangeRequest');

        if ($cab_cr_flag) {
            request()->request->add(['cab_cr_flag' => true]);
        }

        // Debug output
        /*Log::info('Edit method called', [
            'id' => $id,
            'cab_cr_flag' => $cab_cr_flag,
            'has_check_business' => request()->has('check_business'),
            'check_business_value' => request('check_business'),
            'all_request_data' => request()->all()
        ]);*/

        if (request()->has('check_dm')) {
            $validation = $this->validateDivisionManagerAccess($id);
            if ($validation) {
                return $validation;
            }
        } else {
            if (!$cab_cr_flag) {
                $cr = $this->changerequest->find($id);
                /*Log::info('CR find result', [
                    'cr_found' => $cr ? true : false,
                    'cr_id' => $cr->id ?? null
                ]);*/
                if (!$cr) {
                    return redirect()->to('/change_request')->with('status', 'You have no access to edit this CR');
                }
            }
        }

        $cr = $this->getCRForEdit($id, $cab_cr_flag);

        if (is_a($cr, 'Illuminate\Http\RedirectResponse')) {
            return $cr;
        }

        $editData = $this->changeRequestService->prepareEditData($cr, $id);
        $editData['cab_cr_flag'] = $cab_cr_flag;

        return view("{$this->view}.edit", $editData);
    }

    public function download(int $id)
    {
        try {
            return $this->attachmentService->download($id);
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function deleteFile(int $id)
    {
        try {
            $this->attachmentService->deleteFile($id);
            return redirect()->back()->with('success', 'File deleted successfully.');
        } catch (Exception $e) {
            Log::warning('Unauthorized file deletion attempt or file not found', [
                'user_id' => auth()->id(),
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->withErrors($e->getMessage());
        }
    }

    public function update(changeRequest_Requests $request, int $id)
    {

        $this->authorize('Edit ChangeRequest');

        DB::beginTransaction();
        try {
            $this->attachmentService->validateAttachments($request);

            $this->changeRequestService->updateChangeRequest($id, $request);

            $this->attachmentService->handleFileUploads($request, $id);

            DB::commit();

            /*Log::info('Change request updated successfully', [
                'cr_id' => $id,
                'user_id' => auth()->id(),
            ]);*/

            $cr = Change_request::find($id);

            return redirect()->to('/search/result?search=' . $cr->cr_no)->with('status', 'Updated Successfully');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update change request', [
                'cr_id' => $id,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function handleDivisionManagerAction(Request $request)
    {
        $cr_id = $request->query('crId');
        $action = $request->query('action');
        $token = $request->query('token');

        if (!$cr_id || !$action || !$token) {
            return $this->renderActionResponse(false, 'Error', 'Invalid request. Missing parameters.', 400);
        }

        $cr = Change_request::find($cr_id);
        if (!$cr) {
            return $this->renderActionResponse(false, 'Error', 'Change Request not found.', 404);
        }

        $expectedToken = $this->changeRequestService->generateSecurityToken($cr);
        if ($token !== $expectedToken) {
            return $this->renderActionResponse(false, 'Error', 'Unauthorized access. Invalid token.', 403);
        }

        $current_status = $cr->getCurrentStatus()->new_status_id;

        if (
            $current_status != \App\Services\StatusConfigService::getStatusId('business_approval') ||
            $current_status != \App\Services\StatusConfigService::getStatusId('division_manager_approval')
        ) {
            $rejectStatuses = [
                \App\Services\StatusConfigService::getStatusId('Reject'),
                \App\Services\StatusConfigService::getStatusId('Reject', ' kam'),
            ];

            $message = in_array($current_status, $rejectStatuses)
                ? 'You already rejected this CR.'
                : 'You already approved this CR.';

            return $this->renderActionResponse(false, 'Error', $message, 400);
        }

        try {
            $this->changeRequestService->processDivisionManagerAction($cr, $action, $current_status);

            $message = $action === 'approve'
                ? "CR #{$cr->id} has been successfully approved."
                : "CR #{$cr->id} has been successfully rejected.";

            return $this->renderActionResponse(true, 'Success', $message, 200);

        } catch (Exception $e) {
            return $this->renderActionResponse(false, 'Error', $e->getMessage(), 500);
        }
    }

    public function my_assignments()
    {
        $this->authorize('My Assignments');

        $collection = $this->changerequest->my_assignments_crs();
        $title = 'My Assignments';

        return view("{$this->view}.my_assignments", compact('collection', 'title'));
    }

    public function my_crs()
    {
        $crs = $this->changerequest->my_crs();
        $my_crs = MyCRSResource::collection($crs);

        return response()->json(['data' => $my_crs], 200);
    }

    public function list_crs_by_user(Request $request)
    {
        $this->authorize('Show My CRs');

        $user = auth()->user();
        $workflow_type = $request->input('workflow_type', 'In House');

        $status_promo_view = $this->getPromoStatusView($workflow_type);

        $collection = $this->buildUserCRQuery($user->id, $workflow_type)->get();

        $crs_in_queues = 0;
        $user_name = $user->user_name;

        return view("{$this->view}.CRsByuser", compact(
            'collection',
            'user_name',
            'crs_in_queues',
            'status_promo_view'
        ));
    }

    public function exportUserCreatedCRs(Request $request)
    {
        $user = auth()->user();
        $workflow_type = $request->input('workflow_type', 'In House');

        $roles_name = auth()->user()->roles->pluck('name');
        $current_user_is_just_a_viewer = count($roles_name) === 1 && $roles_name[0] === 'Viewer';

        $filename = 'user_created_crs_' . $user->user_name . '_' . str_replace(' ', '_', $workflow_type) . '_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

        return Excel::download(new \App\Exports\UserCreatedCRsExport($user->id, $current_user_is_just_a_viewer, $workflow_type), $filename);
    }

    public function reorderhome()
    {
        $this->authorize('Shift ChangeRequest');

        return view("{$this->view}.shifiting");
    }

    public function reorderChangeRequest(Request $request)
    {
        $this->authorize('Shift ChangeRequest');

        $request->validate([
            'change_request_id' => 'required|exists:change_request,cr_no',
        ]);

        $crId = $request->input('change_request_id');
        $repository = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();
        $result = $repository->reorderTimes($crId);

        if ($result['status']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function approved_active(Request $request)
    {
        $this->changeRequestService->approvedActive($request);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    }

    public function search_result(int $id)
    {
        $cr = '39390';

        return response()->json(['data' => $cr], 200);
    }

    // This was handleDivisionManagerAction1 in original, which was a duplicate/JSON version of handleDivisionManagerAction
    // I will map it to use the service as well, assuming it's needed for a different route.
    public function handleDivisionManagerAction1(Request $request)
    {
        $cr_id = $request->query('crId');
        $action = $request->query('action');
        $token = $request->query('token');
        $workflow = $request->query('workflow');

        if (!$cr_id || !$action || !$token || !$workflow) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid request. Missing parameters.',
            ], 400);
        }

        $cr = Change_request::find($cr_id);
        if (!$cr) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Change Request not found.',
            ], 404);
        }

        $expectedToken = $this->changeRequestService->generateSecurityToken($cr);
        if ($token !== $expectedToken) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized access. Invalid token.',
            ], 403);
        }

        $current_status = Change_request_statuse::where('cr_id', $cr_id)
            ->where('active', '1')
            ->value('new_status_id');

        if (
            !in_array($current_status, [
                \App\Services\StatusConfigService::getStatusId('business_approval'),
                \App\Services\StatusConfigService::getStatusId('business_approval', ' kam'),
                \App\Services\StatusConfigService::getStatusId('division_manager_approval'),
            ])
        ) {
            $rejectStatuses = [
                \App\Services\StatusConfigService::getStatusId('Reject'),
                \App\Services\StatusConfigService::getStatusId('Reject', ' kam'),
            ];

            $message = in_array($current_status, $rejectStatuses)
                ? 'You already rejected this CR.'
                : 'You already approved this CR.';

            return response()->json([
                'isSuccess' => false,
                'message' => $message,
            ], 400);
        }

        try {
            // Note: The original code used a direct update here instead of the helper method in some cases,
            // but the service method should handle it if we pass the right params.
            // However, the original code had specific logic for this method.
            // For now, I'll use the service method which encapsulates the logic.

            // Wait, the original handleDivisionManagerAction1 used 'workflow' param as new_status_id directly.
            // The service method processDivisionManagerAction calculates the new status ID.
            // I should probably use a dedicated method or update the service to support this.
            // But looking at the code, it seems this method is for "approved_active" route which might be different.

            // Let's use the repo directly here as it was in the original, or add a specific method to service.
            // Since I didn't add a specific method for this exact logic (which takes 'workflow' as ID), I'll implement it here using repo for now
            // or better, add it to service?
            // The logic is simple enough: update status to $workflow.

            $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();
            $updateRequest = new Request([
                'old_status_id' => $current_status,
                'new_status_id' => $workflow,
            ]);
            $repo->UpateChangeRequestStatus($cr_id, $updateRequest);

            $message = $action === 'approve'
                ? "CR #{$cr_id} has been successfully approved."
                : "CR #{$cr_id} has been successfully rejected.";

            return response()->json([
                'isSuccess' => true,
                'message' => $message,
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to process division manager action (JSON)', [
                'cr_id' => $cr_id,
                'action' => $action . ' - ' . $workflow . ' - ' . $current_status,
                'error' => $e,
            ]);

            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to process action. Please try again.',
                'error' => $e,
            ], 500);
        }
    }

    public function handlePendingCap(Request $request)
    {

        try {
            $message = $this->changeRequestService->handlePendingCap($request);

            return response()->json([
                'status' => 200,
                'isSuccess' => true,
                'message' => $message,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update_attach(attachments_CRS_Request $request)
    {
        $this->authorize('Edit ChangeRequest');

        if (!$request->hasFile('filesdata')) {
            return response()->json([
                'success' => false,
                'message' => 'No files provided',
            ], 400);
        }

        try {
            $cr_id = $request->get('id');
            $this->attachmentService->addFiles($request->file('filesdata'), $cr_id);

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to upload attachment files', [
                'cr_id' => $request->get('id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload files',
            ], 500);
        }
    }

    public function uploadDevAttachments(Request $request, int $cr_id)
    {
        $this->authorize('Upload CR Attachments');

        if (!$request->hasFile('technical_attachments')) {
            return response()->json([
                'success' => false,
                'message' => 'No files provided',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Validate attachments
            $this->attachmentService->validateAttachments($request);

            $this->changeRequestService->updateChangeRequest($cr_id, $request);

            // Handle file uploads
            $this->attachmentService->handleFileUploads($request, $cr_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to Upload CR Attachments', [
                'cr_id' => $cr_id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        $this->authorize('Delete ChangeRequest');

        try {
            Log::info('Change request deletion attempted', [
                'cr_id' => $id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Change request deleted successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to delete change request', [
                'cr_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete change request',
            ], 500);
        }
    }

    public function showTestableForm()
    {
        $this->authorize('Edit Testable Form');

        return view($this->view . '.testable_form');
    }

    public function updateTestableFlag(Request $request)
    {
        $this->authorize('Edit Testable Form');

        $request->validate([
            'cr_number' => 'required|exists:change_request,cr_no',
            'testable' => 'required|in:0,1',
        ]);
        DB::beginTransaction();
        try {
            $id = Change_request::where('cr_no', $request->cr_number)->firstOrFail()->id;

            $cr_id = $this->changerequest->updateTestableFlag($id, $request);

            if ($cr_id === false) {
                throw new Exception('Failed to update change request');
            }

            DB::commit();

            /*Log::info('Change request updated successfully', [
                'cr_id' => $id,
                'user_id' => auth()->id(),
            ]);*/

            return redirect()->back()->with('status', 'Updated Successfully');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update change request', [
                'cr_id' => $id ?? null,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to update change request.');
        }
    }

    public function showTopManagementForm(Request $request)
    {
        $this->authorize('Access Top Management CRS');

        // Get active tab from request or default to the first available workflow
        $activeTabId = $request->get('tab');
        if (!$activeTabId) {
            // Get the first workflow that has top management CRs
            $firstWorkflow = \App\Models\WorkFlowType::whereHas('changeRequests', function ($query) {
                $query->where('top_management', '1');
            })
                ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
                ->orderBy('id')
                ->first();

            $activeTabId = $firstWorkflow ? $firstWorkflow->id : 9;
        }

        // Get all workflow types that have CRs with top_management = 1 and parent_id is not null
        $workflows_with_top_management_crs = \App\Models\WorkFlowType::whereHas('changeRequests', function ($query) {
            $query->where('top_management', '1');
        })
            ->whereNotNull('parent_id')
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->orderBy('id')
            ->get();

        // If no workflows have top management CRs, get all active workflow types with parent_id is not null
        if ($workflows_with_top_management_crs->count() === 0) {
            $workflows_with_top_management_crs = \App\Models\WorkFlowType::active()
                ->whereNotNull('parent_id')
                ->orderBy('id')
                ->get();
        }

        // Get top management CRs grouped by workflow type
        $top_management_crs_by_workflow = [];
        foreach ($workflows_with_top_management_crs as $workflow) {
            $top_management_crs_by_workflow[$workflow->id] = Change_request::where('top_management', '1')
                ->where('workflow_type_id', $workflow->id)
                ->orderBy('cr_no', 'desc')
                ->get();
        }

        // Get all CRs with top_management = 1 (for backward compatibility)
        $changeRequests = Change_request::where('top_management', '1')
            ->orderBy('cr_no', 'desc')
            ->get();

        return view($this->view . '.top_management_crs', compact(
            'changeRequests',
            'workflows_with_top_management_crs',
            'top_management_crs_by_workflow',
            'activeTabId'
        ));
    }

    public function exportTopManagementTable()
    {
        $this->authorize('Access Top Management CRS'); // permission check

        return Excel::download(new \App\Http\Controllers\ChangeRequest\TopManagementMultiSheetExport, 'Top-Management-CRs.xlsx');
    }

    public function updateTopManagementFlag(Request $request)
    {
        $this->authorize('Edit Top Management Form');

        $request->validate([
            'cr_number' => 'required|exists:change_request,cr_no',
            'top_management' => 'required|in:0,1',
        ]);
        DB::beginTransaction();
        try {
            $id = Change_request::where('cr_no', $request->cr_number)->firstOrFail()->id;

            $cr_id = $this->changerequest->updateTopManagementFlag($id, $request);

            if ($cr_id === false) {
                throw new Exception('Failed to update change request');
            }

            DB::commit();

            /*Log::info('Change request updated successfully', [
                'cr_id' => $id,
                'user_id' => auth()->id(),
            ]);*/

            return redirect()->back()->with('status', 'Updated Successfully');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update change request', [
                'cr_id' => $id ?? null,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to update change request.');
        }
    }

    public function showAddAttachmentsForm()
    {
        $this->authorize('Admin Add Attachments and Feedback');

        return view($this->view . '.add_attachments');
    }

    public function storeAttachments(Request $request)
    {
        $this->authorize('Admin Add Attachments and Feedback');

        $request->validate([
            'cr_number' => 'required|exists:change_request,cr_no',
            'business_feedback' => 'nullable|string|max:5000',
            'technical_feedback' => 'nullable|string|max:5000',
        ], [
            'cr_number.required' => 'CR number is required',
            'cr_number.exists' => 'The specified CR number does not exist',
        ]);

        try {
            $this->attachmentService->validateAttachments($request);
        } catch (Exception $e) {
            return redirect()->back()->withErrors($e->getMessage())->withInput();
        }

        if ($request->hasFile('business_attachments') || $request->hasFile('technical_attachments')) {
            $changeRequest = Change_request::where('cr_no', $request->cr_number)
                ->select('id', 'workflow_type_id')
                ->firstOrFail();
            $status = DB::table('change_request_statuses')
                ->where('cr_id', $changeRequest->id)
                ->where('active', '1')
                ->orderBy('id', 'desc')
                ->first();

            $status_id = $status->new_status_id ?? null;
            if ($changeRequest->workflow_type_id == 3) {
                if (
                    !in_array($status_id, [
                        config('change_request.status_ids.pending_production_deployment_in_house'),
                        config('change_request.status_ids.pending_stage_deployment_in_house'),
                    ])
                ) {
                    return redirect()->back()
                        ->with('error', 'Change request is not in pending production deployment or pending stage deployment status.')
                        ->withInput();
                }
            }
        }

        DB::beginTransaction();
        try {
            $id = Change_request::where('cr_no', $request->cr_number)->firstOrFail()->id;

            $cr_id = $this->changerequest->addFeedback($id, $request);

            if ($cr_id === false) {
                throw new Exception('Failed to update change request');
            }

            $this->attachmentService->handleFileUploads($request, $id);

            DB::commit();

            /*Log::info('Change request updated successfully', [
                'cr_id' => $id,
                'user_id' => auth()->id(),
            ]);*/

            return redirect()->back()->with('status', 'Updated Successfully');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update change request', [
                'cr_id' => $id ?? null,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to update change request.');
        }
    }

    public function unreadNotifications()
    {
        $user = auth()->user();
        $unreadNotifications = $user->unreadNotifications;

        $filteredNotifications = $unreadNotifications->filter(function ($notification) use ($user) {
            $groupIdInNotification = $notification->data['user_action_id'] ?? null;
            if (isset($groupIdInNotification)) {
                if ($groupIdInNotification == $user->id) {
                    return true;
                }
            }
            return false;
        });

        if ($filteredNotifications->isEmpty()) {
            return;
        }

        return response()->json($filteredNotifications);
    }

    private function shareViewData(): void
    {
        view()->share([
            'view' => $this->view,
            'route' => 'change_request',
            'title' => 'List Change Requests',
            'form_title' => 'CR',
        ]);
    }

    private function validateDivisionManagerAccess(int $id)
    {
        $user_email = strtolower(auth()->user()->email);
        $division_manager = strtolower(
            Change_request::where('id', $id)->value('division_manager')
        );
        $current_status = Change_request_statuse::where('cr_id', $id)
            ->where('active', '1')
            ->value('new_status_id');

        if ($user_email === $division_manager && $current_status != '22') {
            return response()->view("{$this->view}.action_response", [
                'isSuccess' => false,
                'title' => 'Error',
                'message' => 'You already took action on this CR',
                'status' => 400,
            ], 400);
        }

        return null;
    }

    private function getCRForEdit(int $id, bool $cab_cr_flag)
    {
        if ($cab_cr_flag) {
            return $this->validateCabCR($id);
        }

        $cr = $this->changerequest->findById($id);
        if (!$cr) {
            return redirect()->back()->with('status', 'CR not exists');
        }

        $cr = $this->changerequest->find($id);

        if (!$cr) {
            return redirect()->to('/change_request')->with('status', 'You have no access to edit this CR');
        }

        return $cr;
    }

    private function validateCabCR(int $id)
    {
        $cr = $this->changerequest->findCr($id);

        if (!$cr) {
            return redirect()->back()->with('status', 'You have no access to edit this CR');
        }

        if (empty($cr->cab_cr) || $cr->cab_cr->status == '2') {
            return redirect()->to('/')->with('status', 'CR already rejected');
        }

        $user_id = auth()->id();
        $cr_cab_user = $cr->cab_cr->cab_cr_user->pluck('user_id')->toArray();

        if (!in_array($user_id, $cr_cab_user)) {
            return redirect()->to('/')->with('status', 'You have no access to edit this CR');
        }

        $check_if_approve = $cr->cab_cr->cab_cr_user
            ->where('user_id', $user_id)
            ->where('status', '1')
            ->first();

        if ($check_if_approve) {
            return redirect()->to('/')->with('status', 'You already approved before');
        }

        return $cr;
    }

    private function renderActionResponse(bool $isSuccess, string $title, string $message, int $status)
    {
        return response()->view("{$this->view}.action_response", [
            'isSuccess' => $isSuccess,
            'title' => $title,
            'message' => $message,
            'status' => $status,
        ], $status);
    }

    private function getPromoStatusView(string $workflow_type): ?array
    {
        if ($workflow_type !== 'Promo') {
            return null;
        }

        $group_promo = Group::with('group_statuses')->find(50);

        return $group_promo
            ? $group_promo->group_statuses
                ->where('type', \App\Models\GroupStatuses::VIEWBY)
                ->pluck('status.id')
                ->toArray()
            : null;
    }

    private function buildUserCRQuery(int $user_id, ?string $workflow_type)
    {
        $query = Change_request::with(['release', 'CurrentRequestStatuses'])
            ->where('requester_id', $user_id);

        if ($workflow_type) {
            $workflow_type_id = WorkFlowType::where('name', $workflow_type)
                ->whereNotNull('parent_id')
                ->value('id');

            if ($workflow_type_id) {
                $query->where('workflow_type_id', $workflow_type_id);
            }
        }

        return $query;
        //    return $this->changeRequestService->list_crs_by_user($request);
    }

    public function updateManDaysDate(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|exists:man_days_logs,id',
                'start_date' => 'required|date'
            ]);

            $log = $this->changeRequestService->updateManDaysDate($request->id, $request->start_date);

            return response()->json([
                'success' => true,
                'message' => 'Start Date updated successfully',
                'data' => $log
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // --- Top Management CRS CRUD Methods ---

    public function listTopManagementCrs(Request $request)
    {
        $this->authorize('List Top Management CRS');

        $changeRequests = Change_request::where('top_management', '1')
            ->with(['member', 'application', 'currentRequestStatuses.status'])
            ->orderBy('cr_no', 'desc')
            ->paginate(15);

        return view($this->view . '.top_management_list', compact('changeRequests'));
    }

    public function createTopManagementCr(Request $request)
    {
        $this->authorize('Create Top Management CRS');

        // Get necessary data for the form
        $workflow_type = (new Workflow_type_repository)->get_workflow_all_subtype_without_release();
        $applications = (new ApplicationFactory)->index();

        return view($this->view . '.create_top_management', compact('workflow_type', 'applications'));
    }

    public function storeTopManagementCr(Request $request)
    {
        $this->authorize('Create Top Management CRS');

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'workflow_type_id' => 'required|exists:workflow_type,id',
            'application_id' => 'required|exists:applications,id',
        ]);

        DB::beginTransaction();
        try {
            $changeRequest = Change_request::create([
                'title' => $request->title,
                'description' => $request->description,
                'workflow_type_id' => $request->workflow_type_id,
                'application_id' => $request->application_id,
                'requester_id' => auth()->id(),
                'top_management' => '1',
                'cr_no' => $this->generateCrNumber(),
            ]);

            // Create initial status
            Change_request_statuse::create([
                'change_request_id' => $changeRequest->id,
                'status_id' => $this->getInitialStatusId($request->workflow_type_id),
                'user_id' => auth()->id(),
            ]);

            DB::commit();
            return redirect()->route('top_management_crs.list')
                ->with('success', 'Top Management CR created successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating top management CR: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error creating Top Management CR. Please try again.')
                ->withInput();
        }
    }

    public function editTopManagementCr($id)
    {
        $this->authorize('Edit Top Management Form');

        $changeRequest = Change_request::where('top_management', '1')
            ->findOrFail($id);

        $workflow_type = (new Workflow_type_repository)->get_workflow_all_subtype_without_release();
        $applications = (new ApplicationFactory)->index();

        return view($this->view . '.edit_top_management', compact('changeRequest', 'workflow_type', 'applications'));
    }

    public function updateTopManagementCr(Request $request, $id)
    {
        $this->authorize('Edit Top Management Form');

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'workflow_type_id' => 'required|exists:workflow_type,id',
            'application_id' => 'required|exists:applications,id',
        ]);

        $changeRequest = Change_request::where('top_management', '1')
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            $changeRequest->update([
                'title' => $request->title,
                'description' => $request->description,
                'workflow_type_id' => $request->workflow_type_id,
                'application_id' => $request->application_id,
            ]);

            DB::commit();
            return redirect()->route('top_management_crs.list')
                ->with('success', 'Top Management CR updated successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating top management CR: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error updating Top Management CR. Please try again.')
                ->withInput();
        }
    }

    public function deleteTopManagementCr($id)
    {
        $this->authorize('Delete Top Management CRS');

        $changeRequest = Change_request::where('top_management', '1')
            ->findOrFail($id);

        DB::beginTransaction();
        try {
            // Delete related records first
            $changeRequest->currentRequestStatuses()->delete();
            $changeRequest->delete();

            DB::commit();
            return redirect()->route('top_management_crs.list')
                ->with('success', 'Top Management CR deleted successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting top management CR: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error deleting Top Management CR. Please try again.');
        }
    }

    // --- Helper Methods ---

    private function generateCrNumber()
    {
        $latestCr = Change_request::orderBy('id', 'desc')->first();
        $nextNumber = $latestCr ? intval(substr($latestCr->cr_no, 2)) + 1 : 1;
        return 'CR' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    private function getInitialStatusId($workflowTypeId)
    {
        // Get the first status for the workflow type
        $workflowType = WorkFlowType::find($workflowTypeId);
        return $workflowType ? $workflowType->statuses()->first()->id : 1;
    }

    /**
     * Preload all workflow data to prevent N+1 queries when rendering the view.
     * This caches workflows by type_id and from_status_id for quick lookup.
     */
    private function preloadWorkflowData(array $workflowTypeIds): void
    {
        $workflows = NewWorkFlow::whereIn('type_id', $workflowTypeIds)
            ->get()
            ->groupBy(function ($workflow) {
                return $workflow->type_id . '_' . $workflow->from_status_id;
            });

        app()->instance('preloaded_workflows', $workflows);
    }
}
