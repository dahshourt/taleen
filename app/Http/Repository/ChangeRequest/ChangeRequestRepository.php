<?php

namespace App\Http\Repository\ChangeRequest;

use App\Contracts\ChangeRequest\ChangeRequestRepositoryInterface;
use App\Http\Repository\Logs\LogRepository;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\DeploymentApprovalCr;
use App\Models\DeploymentApprovalCrUser;
use App\Models\NewWorkFlow;
use App\Services\ChangeRequest\ChangeRequestCreationService;
use App\Services\ChangeRequest\ChangeRequestSchedulingService;
use App\Services\ChangeRequest\ChangeRequestSearchService;
use App\Services\ChangeRequest\ChangeRequestStatusService;
use App\Services\ChangeRequest\ChangeRequestUpdateService;
use App\Services\ChangeRequest\ChangeRequestValidationService;
use App\Services\ChangeRequest\SrWorkflow\SrChangeRequestUpdateService;

class ChangeRequestRepository implements ChangeRequestRepositoryInterface
{
    protected $creationService;

    protected $updateService;

    protected $statusService;

    protected $schedulingService;

    protected $searchService;

    protected $validationService;

    protected $logRepository;

    public function __construct()
    {
        $this->creationService = new ChangeRequestCreationService();
        $this->updateService = new ChangeRequestUpdateService();
        $this->statusService = new ChangeRequestStatusService();
        $this->schedulingService = new ChangeRequestSchedulingService();
        $this->searchService = new ChangeRequestSearchService();
        $this->validationService = new ChangeRequestValidationService();
        $this->logRepository = new LogRepository();
    }

    // Basic CRUD Operations
    public function findById(int $id): ?Change_request
    {
        return Change_request::find($id);
    }

    public function ListCRsByWorkflowType($workflow_type_id)
    {
        return Change_request::where('workflow_type_id', $workflow_type_id)->get();
    }

    public function LastCRNo()
    {
        $ChangeRequest = Change_request::orderby('id', 'desc')->first();

        return isset($ChangeRequest) ? $ChangeRequest->cr_no + 1 : 1;
    }

    public function AddCrNobyWorkflow($workflow_type_id): int
    {
        return $this->creationService->generateCrNumber($workflow_type_id);
    }

    public function create(array $data): array
    {
        return $this->creationService->create($data);
    }

    public function update($id, $request)
    {

        $cr = Change_request::find($id);
        if ($cr && $cr->workflow_type_id == 15) {
            $srUpdateService = new SrChangeRequestUpdateService();
            return $srUpdateService->update($id, $request);
        }

        return $this->updateService->update($id, $request);
    }

    public function updateTestableFlag($id, $request)
    {
        return $this->updateService->updateTestableFlag($id, $request);
    }

    public function updateTopManagementFlag($id, $request)
    {
        return $this->updateService->updateTopManagementFlag($id, $request);
    }

    public function addFeedback($id, $request)
    {
        return $this->updateService->addFeedback($id, $request);
    }

    public function delete($id)
    {
        return Change_request::destroy($id);
    }

    // Listing methods
    public function getAll($group = null)
    {
        return $this->searchService->getAll($group);
    }

    public function getAllForLisCRs(array $workflow_type_ids, $group = null): array
    {
        return $this->searchService->getAllForLisCRs($workflow_type_ids, $group);
    }

    public function getAllWithoutPagination($group = null)
    {
        return $this->searchService->getAllWithoutPagination($group);
    }

    public function dvision_manager_cr($group = null)
    {
        return $this->searchService->divisionManagerCr($group);
    }

    public function cr_pending_cap()
    {
        return $this->searchService->cr_pending_cap();
    }// cr_hold_promo

    public function cr_hold_promo()
    {
        return $this->searchService->cr_hold_promo();
    }// cr_hold_promo

    public function my_assignments_crs()
    {
        return $this->searchService->myAssignmentsCrs();
    }

    public function my_crs()
    {
        return $this->searchService->myCrs();
    }

    // Search methods
    public function find($id)
    {
        return $this->searchService->find($id);
    }

    public function findCr($id)
    {
        return $this->searchService->findCr($id);
    }

    public function AdvancedSearchResult($getall = 0)
    {
        return $this->searchService->advancedSearch($getall);
    }

    public function searhchangerequest($id)
    {
        return $this->searchService->searchChangeRequest($id);
    }

    // Status methods
    public function ShowChangeRequestData($id, $group)
    {
        return $this->searchService->showChangeRequestData($id, $group);
    }

    public function findWithReleaseAndStatus($id)
    {
        return $this->searchService->findWithReleaseAndStatus($id);
    }

    // Scheduling methods
    public function reorderTimes($crId)
    {
        return $this->schedulingService->reorderTimes($crId);
    }

    public function holdPromo($crId)
    {

        return $this->schedulingService->holdPromo($crId);
    }

    public function reorderChangeRequests($crId)
    {
        return $this->schedulingService->reorderChangeRequests($crId);
    }

    public function reorderCRQueues(string $crNumber)
    {
        return $this->schedulingService->reorderCRQueues($crNumber);
    }

    // Status update methods
    public function UpateChangeRequestStatus($id, $request)
    {
        $ChangeRequest = Change_request::find($id);

        $this->statusService->updateChangeRequestStatus($id, $request);

        $this->logRepository->logCreate($id, $request, $ChangeRequest, 'update');

        return true;
    }

    public function UpateChangeRequestReleaseStatus($id, $request)
    {
        return $this->statusService->updateChangeRequestReleaseStatus($id, $request);
    }

    public function updateChangeRequestStatusForFinalConfirmation($id, $request, string $technical_feedback)
    {
        return $this->statusService->updateChangeRequestStatusForFinalConfirmation($id, $request, $technical_feedback);
    }

    // Statistics methods
    public function CountCrsPerSystem($workflow_type)
    {
        $collection = Change_request::groupBy('application_id')
            ->selectRaw('count(*) as total, application_id')
            ->where('workflow_type_id', $workflow_type)
            ->get();

        return $collection;
    }

    public function CountCrsPerStatus()
    {
        $collection = Change_request_statuse::groupBy('new_status_id')
            ->selectRaw('count(*) as total, new_status_id')
            ->where('active', '1')
            ->get();

        return $collection;
    }

    public function CountCrsPerSystemAndStatus($workflow_type)
    {
        $collection = Change_request_statuse::whereHas('ChangeRequest', function ($q) use ($workflow_type) {
            $q->where('workflow_type_id', $workflow_type);
        })
            ->groupBy('new_status_id')
            ->selectRaw('count(*) as total, new_status_id')
            ->where('active', '1')
            ->get();

        return $collection;
    }

    // Workflow methods
    public function getWorkFollowDependOnApplication($id)
    {
        $app = Application::where('id', $id)->first();

        return $app->workflow_type_id;
    }

    public function get_change_request_by_release($release_id)
    {
        return Change_request::with('CurrentRequestStatuses')
            ->where('release_name', $release_id)
            ->where('workflow_type_id', 5)
            ->get();
    }

    // Calendar methods
    public function update_to_next_status_calendar()
    {
        // This is now handled by ProcessCalendarStatusUpdatesJob
        dispatch(new \App\Jobs\ChangeRequest\ProcessCalendarStatusUpdatesJob());
    }

    /* ======================================================================
     |              DEPLOYMENT APPROVAL (Capacity + Division Managers)
     * ====================================================================== */

    public function approveDeployment(int $crId, int $userId): array
    {
        $deploymentApprovalCr = DeploymentApprovalCr::where('cr_id', $crId)
            ->whereRaw('CAST(status AS CHAR) = ?', ['0'])
            ->first();

        if (!$deploymentApprovalCr) {
            return ['success' => false, 'message' => 'No pending deployment approval found for this CR.'];
        }

        $userApproval = $deploymentApprovalCr->deploymentApprovalCrUsers()
            ->where('user_id', $userId)
            ->where('status', '0')
            ->first();

        if (!$userApproval) {
            return ['success' => false, 'message' => 'You have already acted on this deployment approval.'];
        }

        $userApproval->update(['status' => '1']);

        $user = \App\Models\User::find($userId);
        $userName = $user ? $user->user_name : 'Unknown';

        $countAllUsers = $deploymentApprovalCr->deploymentApprovalCrUsers()->count();
        $countApprovedUsers = $deploymentApprovalCr->deploymentApprovalCrUsers()->where('status', '1')->count();

        $this->logRepository->create([
            'cr_id' => $crId,
            'user_id' => $userId,
            'log_text' => "Deployment Approval approved by '{$userName}'. Waiting for other approvers ({$countApprovedUsers}/{$countAllUsers})",
        ]);

        if ($countAllUsers == $countApprovedUsers) {
            $deploymentApprovalCr->update(['status' => '1']);

            $this->logRepository->create([
                'cr_id' => $crId,
                'user_id' => $userId,
                'log_text' => "Deployment Approval fully approved. Last approved by '{$userName}'",
            ]);

            // If CR is non-testable, move it to the next status (approve path)
            //dd($countAllUsers, $countApprovedUsers);
            $this->moveNonTestableCrAfterDeploymentApproval($crId, $userId, 'approve');

            return ['success' => true, 'fully_approved' => true, 'message' => 'Deployment fully approved.'];
        }



        return ['success' => true, 'fully_approved' => false, 'message' => 'Approval recorded. Waiting for other approvers.'];
    }

    protected function moveNonTestableCrAfterDeploymentApproval(int $crId, int $userId, string $action): void
    {
        $cr = Change_request::find($crId);
        if (!$cr) {
            return;
        }

        // Check testable from custom fields first, fallback to CR column
        $testableCustomField = $cr->change_request_custom_fields
            ->where('custom_field_name', 'testable')
            ->pluck('custom_field_value')
            ->first();

        $isTestable = $testableCustomField !== null ? (bool) $testableCustomField : (bool) $cr->testable;

        if ($isTestable) {
            return;
        }

        $pendingOperationalId = \App\Services\StatusConfigService::getStatusId('pending_operational_dm_capacity_approval');

        if (!$pendingOperationalId) {
            return;
        }

        // workflow_type: 0 = approve (forward), 1 = reject
        $workflowType = $action === 'approve' ? '0' : '1';

        $workflow = NewWorkFlow::where('from_status_id', $pendingOperationalId)
            ->where('type_id', $cr->workflow_type_id)
            ->where('workflow_type', $workflowType)
            ->active()
            ->first();

        if (!$workflow) {
            return;
        }

        $updateRequest = new \Illuminate\Http\Request([
            'old_status_id' => $pendingOperationalId,
            'new_status_id' => $workflow->id,
            'user_id' => $userId,
        ]);

        $this->updateService->update($crId, $updateRequest);
    }

    public function rejectDeployment(int $crId, int $userId): array
    {
        $deploymentApprovalCr = DeploymentApprovalCr::where('cr_id', $crId)
            ->whereRaw('CAST(status AS CHAR) = ?', ['0'])
            ->first();

        if (!$deploymentApprovalCr) {
            return ['success' => false, 'message' => 'No pending deployment approval found for this CR.'];
        }

        $userApproval = $deploymentApprovalCr->deploymentApprovalCrUsers()
            ->where('user_id', $userId)
            ->where('status', '0')
            ->first();

        if (!$userApproval) {
            return ['success' => false, 'message' => 'You have already acted on this deployment approval.'];
        }

        $userApproval->update(['status' => '2']);
        $deploymentApprovalCr->update(['status' => '2']);

        $user = \App\Models\User::find($userId);
        $userName = $user ? $user->user_name : 'Unknown';

        $this->logRepository->create([
            'cr_id' => $crId,
            'user_id' => $userId,
            'log_text' => "Deployment Approval rejected by '{$userName}'",
        ]);

        // If CR is non-testable, move it to the next status (reject path)
        $this->moveNonTestableCrAfterDeploymentApproval($crId, $userId, 'reject');

        return ['success' => true, 'message' => 'Deployment approval rejected.'];
    }

    public function createDeploymentApproval(int $crId): array
    {
        $existing = DeploymentApprovalCr::where('cr_id', $crId)
            ->whereRaw('CAST(status AS CHAR) = ?', ['0'])
            ->first();

        if ($existing) {
            return ['success' => false, 'message' => 'A pending deployment approval already exists for this CR.'];
        }

        $cr = Change_request::find($crId);
        if (!$cr) {
            return ['success' => false, 'message' => 'Change Request not found.'];
        }

        $application = $cr->application;
        $operationDmId = $application ? $application->operation_dm : null;

        $userIds = [];

        if ($operationDmId) {
            $userIds[] = $operationDmId;
        }

        if ($cr->application_id) {
            $capUserIds = \App\Models\SystemUserCab::where('system_id', $cr->application_id)
                ->where('active', '1')
                ->pluck('user_id')
                ->toArray();
            $userIds = array_merge($userIds, $capUserIds);
        }

        $userIds = array_unique(array_filter($userIds));

        if (empty($userIds)) {
            return ['success' => false, 'message' => 'No approvers found (no Operation DM or Capacity users for this application).'];
        }

        $record = DeploymentApprovalCr::create([
            'cr_id' => $crId,
            'status' => '0',
        ]);

        foreach ($userIds as $userId) {
            DeploymentApprovalCrUser::create([
                'user_id' => $userId,
                'deployment_approval_cr_id' => $record->id,
                'status' => '0',
            ]);
        }

        return ['success' => true, 'message' => 'Deployment approval created.', 'deployment_approval_cr_id' => $record->id];
    }

    /* ======================================================================
     |              SR BUSINESS APPROVAL (Requester + TE Validators)
     * ====================================================================== */

    public function approveSrBusinessApproval(int $crId, int $userId): array
    {
        $srUpdateService = new SrChangeRequestUpdateService();
        return $srUpdateService->approveSrBusinessApproval($crId, $userId);
    }

    public function rejectSrBusinessApproval(int $crId, int $userId): array
    {
        $srUpdateService = new SrChangeRequestUpdateService();
        return $srUpdateService->rejectSrBusinessApproval($crId, $userId);
    }

    public function getRequesterSpecificCrs(int $userId): \Illuminate\Support\Collection
    {
        return Change_request::where('requester_id', $userId)
            ->whereIn('id', function ($query) {
                $query->select('cr_id')
                    ->from('change_request_statuses')
                    ->join('statuses', 'statuses.id', '=', 'change_request_statuses.new_status_id')
                    ->where('change_request_statuses.active', '1')
                    ->where(function ($q) {
                        $q->whereIn('statuses.status_name', ['Pending Business Confirmation', 'SR Business FB'])
                            ->orWhereRaw('LOWER(statuses.status_name) = ?', [strtolower('Pending Business Confirmation')])
                            ->orWhereRaw('LOWER(statuses.status_name) = ?', [strtolower('SR Business FB')]);
                    });
            })
            ->pluck('id');
    }
}
