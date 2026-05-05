<?php

namespace App\Services\ChangeRequest;

use App\Events\ChangeRequestStatusUpdated;
use App\Events\CrDeliveredEvent;
use App\Http\Controllers\Mail\MailController;
use App\Http\Repository\ChangeRequest\ChangeRequestStatusRepository;
use App\Models\Change_request as ChangeRequest;
use App\Models\Change_request_statuse as ChangeRequestStatus;
use App\Models\Group;
use App\Models\GroupStatuses;
use App\Models\NewWorkFlow;
use App\Models\NewWorkFlowStatuses;
use App\Models\Status;
use App\Models\TechnicalCr;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ChangeRequestStatusService
{
    use NeedUpdateWorkflowIds;
    private const TECHNICAL_REVIEW_STATUS = 0;

    private const WORKFLOW_NORMAL = 1;

    private const ACTIVE_STATUS = '1';

    private const INACTIVE_STATUS = '0';

    private const COMPLETED_STATUS = '2';

    public static array $ACTIVE_STATUS_ARRAY = [self::ACTIVE_STATUS, 1];

    public static array $INACTIVE_STATUS_ARRAY = [self::INACTIVE_STATUS, 0];

    public static array $COMPLETED_STATUS_ARRAY = [self::COMPLETED_STATUS, 2];

    // flag to determine if the workflow is active or not to send email to the dev team.
    private $active_flag = '0';

    // Status IDs for dependency checking
    private static ?int $PENDING_CAB_STATUS_ID = null;

    private static ?int $DELIVERED_STATUS_ID = null;

    private static ?int $PENDING_DESIGN_STATUS_ID = null;
    private static ?int $REJECTED_STATUS_ID = null;

    // Status IDs for agreed scope approval workflow
    private static ?int $PENDING_CREATE_AGREED_SCOPE_STATUS_ID = null;
    private static ?int $PENDING_AGREED_SCOPE_SA_STATUS_ID = null;
    private static ?int $PENDING_AGREED_SCOPE_VENDOR_STATUS_ID = null;
    private static ?int $PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID = null;
    private static ?int $REQUEST_DRAFT_CR_DOC_STATUS_ID = null;
    private static ?int $VENDOR_INTERNAL_TEST_STATUS_ID = null;
    private static ?int $ATP_REVIEW_QC_STATUS_ID = null;
    private static ?int $ATP_REVIEW_UAT_STATUS_ID = null;
    private static ?int $REQUEST_UPDATE_ATPS_STATUS_ID = null;

    private $statusRepository;

    private $mailController;

    private ?CrDependencyService $dependencyService = null;

    public function __construct()
    {
        self::$PENDING_CAB_STATUS_ID = \App\Services\StatusConfigService::getStatusId('pending_cab');
        self::$DELIVERED_STATUS_ID = \App\Services\StatusConfigService::getStatusId('Delivered');
        self::$PENDING_DESIGN_STATUS_ID = \App\Services\StatusConfigService::getStatusId('pending_design');
        self::$REJECTED_STATUS_ID = \App\Services\StatusConfigService::getStatusId('Reject');

        self::$PENDING_CREATE_AGREED_SCOPE_STATUS_ID = $this->getStatusIdByName('Pending Create Agreed Scope');
        self::$PENDING_AGREED_SCOPE_SA_STATUS_ID = $this->getStatusIdByName('Pending Agreed Scope Approval-SA');
        self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID = $this->getStatusIdByName('Pending Agreed Scope Approval-Vendor');
        self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID = $this->getStatusIdByName('Pending Agreed Scope Approval-Business');

        self::$REQUEST_DRAFT_CR_DOC_STATUS_ID = $this->getStatusIdByName('Request Draft CR Doc');
        self::$VENDOR_INTERNAL_TEST_STATUS_ID = $this->getStatusIdByName('Vendor Internal Test');
        self::$ATP_REVIEW_QC_STATUS_ID = \App\Services\StatusConfigService::getStatusId('ATP Review_qc');
        self::$ATP_REVIEW_UAT_STATUS_ID = \App\Services\StatusConfigService::getStatusId('ATP Review_UAT');
        self::$REQUEST_UPDATE_ATPS_STATUS_ID = \App\Services\StatusConfigService::getStatusId('Request Update ATPs');

        $this->statusRepository = new ChangeRequestStatusRepository();
        $this->mailController = new MailController();
    }

    private function getRequestUpdateAtpsWorkflowId(int $changeRequestId): ?int
    {
        $changeRequest = ChangeRequest::find($changeRequestId);
        if (!$changeRequest) {
            return null;
        }

        $currentStatusId = $changeRequest->status_id;

        if (!in_array($currentStatusId, [self::$ATP_REVIEW_QC_STATUS_ID, self::$ATP_REVIEW_UAT_STATUS_ID])) {
            return null;
        }

        $workflow = NewWorkFlow::where('from_status_id', $currentStatusId)
            ->whereHas('workflowstatus', function ($query) {
                $query->where('to_status_id', self::$REQUEST_UPDATE_ATPS_STATUS_ID);
            })
            ->active()
            ->first();

        return $workflow?->id;
    }

    private function handleRequestUpdateAtpsTransition(int $changeRequestId, array $statusData): void
    {
        $changeRequest = ChangeRequest::findOrFail($changeRequestId);
        $currentStatusId = $changeRequest->status_id;

        $statusToDeactivate = null;

        if ($currentStatusId == self::$ATP_REVIEW_QC_STATUS_ID) {
            $statusToDeactivate = self::$ATP_REVIEW_UAT_STATUS_ID;
        } elseif ($currentStatusId == self::$ATP_REVIEW_UAT_STATUS_ID) {
            $statusToDeactivate = self::$ATP_REVIEW_QC_STATUS_ID;
        }

        if ($statusToDeactivate) {
            ChangeRequestStatus::where('change_request_id', $changeRequestId)
                ->where('status_id', $statusToDeactivate)
                ->where('active', 1)
                ->update(['active' => 2]);
        }

        ChangeRequestStatus::create([
            'change_request_id' => $changeRequestId,
            'status_id' => self::$REQUEST_UPDATE_ATPS_STATUS_ID,
            'user_id' => $statusData['user_id'] ?? auth()->id(),
            'active' => 1,
            'comment' => $statusData['comment'] ?? 'Transition to Request Update ATPs',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $changeRequest->update([
            'status_id' => self::$REQUEST_UPDATE_ATPS_STATUS_ID,
            'updated_at' => now(),
        ]);
    }

    private function haveBothWorkflowsReachedMergePoint(int $crId, string $mergeStatusName): bool
    {
        $mergeStatus = Status::where('name', $mergeStatusName)->first();

        if (!$mergeStatus) {
            Log::error('Merge status not found', ['status_name' => $mergeStatusName]);
            return false;
        }

        $mergeStatusRecords = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $mergeStatus->id)
            ->get();

        if ($mergeStatusRecords->isEmpty()) {
            return false;
        }

        $uniqueSourceStatuses = $mergeStatusRecords->pluck('old_status_id')->unique();
        $bothReached = $uniqueSourceStatuses->count() >= 2;

        if (!$bothReached) {
            Log::warning('Merge point not ready - both workflows have not reached it', [
                'cr_id' => $crId,
                'reached_count' => $uniqueSourceStatuses->count(),
                'required_count' => 2
            ]);
        }

        return $bothReached;
    }

    private function areBothWorkflowsCompleteById(int $crId, int $mergeStatusId): bool
    {
        $mergeRecords = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $mergeStatusId)
            ->get();

        if ($mergeRecords->isEmpty()) {
            return false;
        }

        $uniquePaths = $mergeRecords->pluck('old_status_id')->unique();
        return $uniquePaths->count() >= 2;
    }

    private function activatePendingMergeStatus(int $crId, array $statusData): void
    {
        $mergeStatusName = 'Pending Update Agreed Requirements';
        $mergeStatus = Status::where('status_name', $mergeStatusName)->first();
        $mergePointStatusId = $mergeStatus ? $mergeStatus->id : null;

        if ($statusData['new_status_id'] == $mergePointStatusId) {
            if ($this->areBothWorkflowsCompleteById($crId, $mergePointStatusId)) {
                $pendingStatuses = ChangeRequestStatus::where('cr_id', $crId)
                    ->where('old_status_id', $mergePointStatusId)
                    ->notActive()
                    ->get();

                foreach ($pendingStatuses as $status) {
                    $status->update(['active' => self::ACTIVE_STATUS]);
                }
            }
        }
    }

    private function requiresMergePointCheck(int $fromStatusId, int $toStatusId): bool
    {
        $workflowStatus = \App\Models\NewWorkflowStatus::where('from_status_id', $fromStatusId)
            ->where('to_status_id', $toStatusId)
            ->first();

        if (!$workflowStatus || !$workflowStatus->workflow) {
            return false;
        }

        return $workflowStatus->workflow->same_time == 1;
    }

    public function updateChangeRequestStatus(int $changeRequestId, $request): bool
    {
        try {
            DB::beginTransaction();

            $statusData = $this->extractStatusData($request);

            $needUpdateWorkflowIds = $this->getAllNeedUpdateWorkflowIds($changeRequestId);

            if (isset($statusData['new_status_id']) && !empty($needUpdateWorkflowIds) && in_array($statusData['new_status_id'], $needUpdateWorkflowIds)) {
                $this->handleNeedUpdateTransition($changeRequestId, $statusData);
                DB::commit();
                return true;
            }

            $requestUpdateAtpsWorkflowId = $this->getRequestUpdateAtpsWorkflowId($changeRequestId);

            if (
                isset($statusData['new_status_id']) &&
                $statusData['new_status_id'] == $requestUpdateAtpsWorkflowId &&
                self::$REQUEST_UPDATE_ATPS_STATUS_ID !== null &&
                $statusData['new_status_id'] == self::$REQUEST_UPDATE_ATPS_STATUS_ID
            ) {
                $this->handleRequestUpdateAtpsTransition($changeRequestId, $statusData);
                DB::commit();
                return true;
            }

            try {
                $iotService = new \App\Services\ChangeRequest\SpecialFlows\IotTcsFlowService();

                if ($iotService->isIotTcsTransition($changeRequestId, $statusData)) {
                    $changeRequest = $this->getChangeRequest($changeRequestId);

                    $context = [
                        'user_id' => Auth::id() ?? null,
                        'application_id' => $changeRequest->application_id ?? null,
                    ];

                    $activeFlag = $iotService->handleIotTcsTransition($changeRequestId, $statusData, $context);
                    $this->active_flag = $activeFlag;

                    DB::commit();

                    event(new ChangeRequestStatusUpdated($changeRequest, $statusData, $request, $this->active_flag));

                    return true;
                }
            } catch (\Throwable $e) {
                Log::error('Error in IotTcsFlowService check', [
                    'cr_id' => $changeRequestId,
                    'error' => $e->getMessage(),
                ]);
            }

            $changeRequest = $this->getChangeRequest($changeRequestId);

            $deliveredStatusId = \App\Services\StatusConfigService::getStatusId('Delivered');
            $productionDeploymentInProgressId = \App\Services\StatusConfigService::getStatusId('production_deployment_in_progress');
            $inHouseWorkflowTypeIds = DB::table('workflow_type')->where('name', 'In House')->pluck('id')->toArray();

            if (isset($statusData['new_status_id']) && $statusData['new_status_id'] == $deliveredStatusId && $statusData['old_status_id'] == $productionDeploymentInProgressId) {
                if (
                    $changeRequest &&
                    $changeRequest->application &&
                    $changeRequest->application->app_support == 1 &&
                    $changeRequest->workflow_type_id != 9
                ) {
                    ChangeRequestStatus::where('cr_id', $changeRequestId)
                        ->where('active', self::ACTIVE_STATUS)
                        ->update(['active' => self::COMPLETED_STATUS]);

                    $lastRecord = ChangeRequestStatus::where('cr_id', $changeRequestId)
                        ->where('active', self::COMPLETED_STATUS)
                        ->orderBy('id', 'desc')
                        ->first();

                    ChangeRequestStatus::create([
                        'cr_id' => $changeRequestId,
                        'old_status_id' => $statusData['old_status_id'],
                        'new_status_id' => $deliveredStatusId,
                        'active' => self::ACTIVE_STATUS,
                        'current_group_id' => $lastRecord ? $lastRecord->current_group_id : null,
                        'user_id' => Auth::id() ?? ($statusData['user_id'] ?? null),
                        'created_at' => now(),
                    ]);

                    DB::commit();

                    $this->checkAndFireDeliveredEvent($changeRequest, $statusData);

                    event(new ChangeRequestStatusUpdated($changeRequest, $statusData, $request, $this->active_flag));

                    return true;
                }
            }

            $workflow = $this->getWorkflow($statusData);
            if ($request->user_id) {
                $userId = $request->user_id;
            } else {
                $userId = $this->getUserId($changeRequest, $request);
            }

            if ($this->isTransitionFromPendingCab($changeRequest, $statusData)) {
                $depService = $this->getDependencyService();
                if ($depService->shouldHoldCr($changeRequestId)) {
                    Log::info('Dependency hold should be applied', [
                        'cr_id' => $changeRequestId,
                        'status_data' => $statusData,
                    ]);
                    $depService->applyDependencyHold($changeRequestId);
                    Log::info('Dependency hold applied', [
                        'cr_id' => $changeRequestId,
                        'status_data' => $statusData,
                    ]);
                    DB::commit();

                    return true;
                }
            }

            $this->processStatusUpdate($changeRequest, $statusData, $workflow, $userId, $request);

            $this->activatePendingMergeStatus($changeRequest->id, $statusData);

            DB::commit();

            $this->checkAndFireDeliveredEvent($changeRequest, $statusData);

            event(new ChangeRequestStatusUpdated($changeRequest, $statusData, $request, $this->active_flag));

            return true;

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error updating change request status', [
                'change_request_id' => $changeRequestId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function handleNeedUpdateTransition(int $crId, array $statusData): void
    {
        $allActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS)
            ->update(['active' => self::COMPLETED_STATUS]);

        $parallelStatusIds = [
            self::$PENDING_AGREED_SCOPE_SA_STATUS_ID,
            self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID,
            self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID,
            self::$REQUEST_DRAFT_CR_DOC_STATUS_ID,
        ];

        $parallelStatusIds = array_filter($parallelStatusIds);

        if (empty($parallelStatusIds)) {
            Log::error('No parallel workflow status IDs configured', [
                'cr_id' => $crId,
                'check_statuses' => [
                    'PENDING_AGREED_SCOPE_SA_STATUS_ID' => self::$PENDING_AGREED_SCOPE_SA_STATUS_ID,
                    'PENDING_AGREED_SCOPE_VENDOR_STATUS_ID' => self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID,
                    'PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID' => self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID,
                    'REQUEST_DRAFT_CR_DOC_STATUS_ID' => self::$REQUEST_DRAFT_CR_DOC_STATUS_ID,
                ]
            ]);
            throw new Exception('Parallel workflow status IDs not configured');
        }

        $archivedCount = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('new_status_id', $parallelStatusIds)
            ->whereIn('active', ['0', '1'])
            ->update(['active' => self::COMPLETED_STATUS]);

        if ($archivedCount === 0) {
            Log::warning('No parallel workflow records found to archive', [
                'cr_id' => $crId,
                'searched_status_ids' => $parallelStatusIds
            ]);
        }

        $lastRecord = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::COMPLETED_STATUS)
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastRecord) {
            Log::error('No archived record found to use as template', [
                'cr_id' => $crId,
                'active_status_checked' => self::COMPLETED_STATUS
            ]);
            throw new Exception("No archived record found for CR {$crId}");
        }

        $stillActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS)
            ->count();

        if ($stillActiveCount > 0) {
            Log::warning('Found active records that should have been archived', [
                'cr_id' => $crId,
                'count' => $stillActiveCount
            ]);

            ChangeRequestStatus::where('cr_id', $crId)
                ->where('active', self::ACTIVE_STATUS)
                ->update(['active' => self::COMPLETED_STATUS]);
        }

        $newRecord = $lastRecord->replicate();
        $newRecord->new_status_id = self::$PENDING_CREATE_AGREED_SCOPE_STATUS_ID;
        $newRecord->old_status_id = $lastRecord->new_status_id;
        $newRecord->active = self::ACTIVE_STATUS;
        $newRecord->created_at = now();
        $newRecord->updated_at = null;
        $newRecord->save();

        $finalActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS)
            ->count();

        if ($finalActiveCount !== 1) {
            Log::error('CRITICAL: Expected 1 active record but found ' . $finalActiveCount, [
                'cr_id' => $crId,
                'active_count' => $finalActiveCount
            ]);
        }
    }

    private function areBothWorkflowsComplete(int $crId): bool
    {
        $mergeStatusName = 'Pending Update Agreed Requirements';
        $mergeStatus = Status::where('status_name', $mergeStatusName)->first();

        if (!$mergeStatus) {
            Log::error('Merge status not found', ['status_name' => $mergeStatusName]);
            return false;
        }

        $workflowANames = ['Request Draft CR Doc', 'Pending Update Draft CR Doc'];
        $workflowAStatusIds = Status::whereIn('status_name', $workflowANames)->pluck('id')->toArray();

        $workflowAReached = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('old_status_id', $workflowAStatusIds)
            ->where('new_status_id', $mergeStatus->id)
            ->exists();

        $workflowBNames = [
            'Pending Agreed Scope Approval-SA',
            'Pending Agreed Scope Approval-Vendor',
            'Pending Agreed Scope Approval-Business',
        ];
        $workflowBStatusIds = Status::whereIn('status_name', $workflowBNames)->pluck('id')->toArray();

        $workflowBReached = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('old_status_id', $workflowBStatusIds)
            ->where('new_status_id', $mergeStatus->id)
            ->exists();

        return $workflowAReached && $workflowBReached;
    }

    private function isIndependentWorkflowA(int $statusId): bool
    {
        $status = Status::find($statusId);
        if (!$status) {
            return false;
        }
        return $status->status_name === 'Request Draft CR Doc';
    }

    private function shouldPreserveForIndependentWorkflow(int $currentStatusId, int $otherStatusId): bool
    {
        $currentIsWorkflowA = $this->isIndependentWorkflowA($currentStatusId);
        $otherIsWorkflowA = $this->isIndependentWorkflowA($otherStatusId);

        if ($currentIsWorkflowA && !$otherIsWorkflowA) {
            return true;
        }

        if (!$currentIsWorkflowA && $otherIsWorkflowA) {
            return true;
        }

        return false;
    }

    private function getActiveStatusBySameTime(int $fromStatusId, int $toStatusId): string
    {
        $workflow = \App\Models\NewWorkFlow::whereHas('workflowstatus', function ($q) use ($fromStatusId, $toStatusId) {
            $q->where('from_status_id', $fromStatusId)
                ->where('to_status_id', $toStatusId);
        })->first();

        if (!$workflow) {
            return self::ACTIVE_STATUS;
        }

        if (isset($workflow->same_time) && $workflow->same_time == 1) {
            return self::COMPLETED_STATUS;
        }

        return self::ACTIVE_STATUS;
    }

    private function validateStatusChange($changeRequest, $statusData, $workflow)
    {
        $currentStatus = $changeRequest->status;
        $newStatus = $statusData['new_status_id'] ?? null;

        \Log::debug('Status change validation', [
            'currentStatus' => $currentStatus,
            'newStatus' => $newStatus,
            'statusData' => $statusData,
        ]);

        if ($currentStatus == $newStatus) {
            return false;
        }

        return true;
    }

    public function extractStatusData($request): array
    {
        $newStatusId = $request['new_status_id'] ?? $request->new_status_id ?? null;
        $oldStatusId = $request['old_status_id'] ?? $request->old_status_id ?? null;
        $newWorkflowId = $request['new_workflow_id'] ?? null;

        return [
            'new_status_id' => $newStatusId,
            'old_status_id' => $oldStatusId,
            'new_workflow_id' => $newWorkflowId,
        ];
    }

    private function getWorkflow(array $statusData): ?NewWorkFlow
    {
        $workflowId = $statusData['new_workflow_id'] ?: $statusData['new_status_id'];
        return NewWorkFlow::find($workflowId);
    }

    private function getChangeRequest(int $id): ChangeRequest
    {
        $changeRequest = ChangeRequest::find($id);
        if (!$changeRequest) {
            throw new Exception("Change request not found: {$id}");
        }
        return $changeRequest;
    }

    private function getUserId(ChangeRequest $changeRequest, $request): int
    {
        if (Auth::check()) {
            return Auth::id();
        }
        if ($changeRequest->division_manager) {
            $user = User::where('email', $changeRequest->division_manager)->first();
            if ($user) {
                return $user->id;
            }
        }

        $assignedTo = $request['assign_to'] ?? null;
        if (!$assignedTo) {
            throw new Exception('Unable to determine user for status update');
        }

        return $assignedTo;
    }

    private function processStatusUpdate(
        ChangeRequest $changeRequest,
        array $statusData,
        NewWorkFlow $workflow,
        int $userId,
        $request
    ): void {
        $technicalTeamCounts = $this->getTechnicalTeamCounts($changeRequest->id, $statusData['old_status_id']);

        $this->updateCurrentStatus($changeRequest->id, $statusData, $workflow, $technicalTeamCounts);

        $this->createNewStatuses($changeRequest, $statusData, $workflow, $userId, $request);

        try {
            $uatPromoService = new \App\Services\ChangeRequest\SpecialFlows\UatPromoFlowService();
            $newActiveStatus = $uatPromoService->handlePendingUatuActivation($changeRequest->id, $statusData, $changeRequest->workflow_type_id);

            if ($newActiveStatus !== null) {
                $this->active_flag = $newActiveStatus;
            }
        } catch (\Throwable $e) {
            Log::error('Error in UatPromoFlowService', [
                'cr_id' => $changeRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getTechnicalTeamCounts(int $changeRequestId, int $oldStatusId): array
    {
        $technicalCr = TechnicalCr::where('cr_id', $changeRequestId)
            ->whereRaw('CAST(status AS CHAR) = ?', ['1'])
            ->first();

        if (!$technicalCr) {
            return ['total' => 0, 'approved' => 0];
        }

        $total = $technicalCr->technical_cr_team()
            ->where('current_status_id', $oldStatusId)
            ->count();

        $approved = $technicalCr->technical_cr_team()
            ->where('current_status_id', $oldStatusId)
            ->whereRaw('CAST(status AS CHAR) = ?', ['1'])
            ->count();

        return ['total' => $total, 'approved' => $approved];
    }

    private function updateCurrentStatus(
        int $changeRequestId,
        array $statusData,
        NewWorkFlow $workflow,
        array $technicalTeamCounts
    ): void {
        if (request()->reference_status) {
            $currentStatus = ChangeRequestStatus::find(request()->reference_status);
        } else {
            $currentStatus = ChangeRequestStatus::where('cr_id', $changeRequestId)
                ->where('new_status_id', $statusData['old_status_id'])
                ->active()
                ->first();

            $allActiveStatuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
                ->active()
                ->get(['id', 'new_status_id', 'old_status_id', 'active']);
        }

        if (!$currentStatus) {
            Log::warning('Current status not found for update', [
                'cr_id' => $changeRequestId,
                'old_status_id' => $statusData['old_status_id'],
            ]);

            return;
        }

        $workflowActive = $workflow->workflow_type == self::WORKFLOW_NORMAL
            ? self::INACTIVE_STATUS
            : self::COMPLETED_STATUS;

        if (is_null($currentStatus->created_at)) {
            Log::warning('Current status has null created_at', [
                'cr_id' => $changeRequestId,
                'status_record_id' => $currentStatus->id,
                'new_status_id' => $currentStatus->new_status_id,
                'active' => $currentStatus->active
            ]);
        }

        $slaDifference = $this->calculateSlaDifference($currentStatus->created_at);

        $shouldUpdate = $this->shouldUpdateCurrentStatus($statusData['old_status_id'], $technicalTeamCounts);
        $newStatus = Status::find($statusData['new_status_id']);

        if ($newStatus && $newStatus->status_name === 'Request Draft CR Doc') {
            $changeRequest = ChangeRequest::find($changeRequestId);
            if (!$changeRequest) {
                Log::error('Change request not found for need_ui_ux update', ['cr_id' => $changeRequestId]);
            }
        }

        if ($shouldUpdate) {
            $updateResult = $currentStatus->update([
                'sla_dif' => $slaDifference,
                'active' => self::COMPLETED_STATUS
            ]);

            $this->handleDependentStatuses($changeRequestId, $currentStatus, $workflowActive);
        } else {
            Log::warning('updateCurrentStatus: Skipped update due to shouldUpdateCurrentStatus=false', [
                'cr_id' => $changeRequestId,
                'status_record_id' => $currentStatus->id
            ]);
        }
    }

    private function shouldUpdateCurrentStatus(int $oldStatusId, array $technicalTeamCounts): bool
    {
        if ($oldStatusId != self::TECHNICAL_REVIEW_STATUS) {
            return true;
        }

        return $technicalTeamCounts['total'] > 0 &&
            $technicalTeamCounts['total'] == $technicalTeamCounts['approved'];
    }

    private function calculateSlaDifference(?string $createdAt): int
    {
        if (!$createdAt) {
            return 0;
        }

        return Carbon::parse($createdAt)->diffInDays(Carbon::now());
    }

    private function handleDependentStatuses(
        int $changeRequestId,
        ChangeRequestStatus $currentStatus,
        string $workflowActive
    ): void {
        $dependentStatuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('old_status_id', $currentStatus->old_status_id)
            ->active()
            ->get();

        $currentIsWorkflowA = $this->isIndependentWorkflowA($currentStatus->new_status_id);

        if ($currentIsWorkflowA) {
            $dependentStatuses->each(function ($status) use ($currentStatus, $changeRequestId) {
                if ($status->id === $currentStatus->id) {
                    return;
                }

                if ($this->shouldPreserveForIndependentWorkflow($currentStatus->new_status_id, $status->new_status_id)) {
                    // preserve
                } else {
                    $status->update(['active' => self::INACTIVE_STATUS]);
                }
            });
        } else {
            if (!$workflowActive) {
                $dependentStatuses->each(function ($status) use ($currentStatus, $changeRequestId) {
                    if ($status->id === $currentStatus->id) {
                        return;
                    }

                    if ($this->shouldPreserveForIndependentWorkflow($currentStatus->new_status_id, $status->new_status_id)) {
                        // preserve
                    } else {
                        $status->update(['active' => self::INACTIVE_STATUS]);
                    }
                });
            }
        }
    }

    private function createNewStatuses(
        ChangeRequest $changeRequest,
        array $statusData,
        NewWorkFlow $workflow,
        int $userId,
        $request
    ): void {

        if (request()->reference_status) {
            $currentStatus = ChangeRequestStatus::find(request()->reference_status);
        } else {
            $currentStatus = ChangeRequestStatus::where('cr_id', $changeRequest->id)
                ->where('new_status_id', $statusData['old_status_id'])
                ->first();
        }

        $oldStatus = Status::find($statusData['old_status_id']);

        $newStatus = null;
        if ($workflow && $workflow->workflowstatus->isNotEmpty()) {
            $newStatus = $workflow->workflowstatus->first()->to_status;
        }

        $shouldCreateParallelWorkflows = false;
        $statusesToCreate = [];

        $workflowBStatuses = [
            'Pending Agreed Scope Approval-SA',
            'Pending Agreed Scope Approval-Vendor',
            'Pending Agreed Scope Approval-Business'
        ];

        if (
            $oldStatus && $newStatus &&
            $newStatus->status_name == 'Pending Create Agreed Scope' &&
            isset($statusData['need_update']) && $statusData['need_update'] === true &&
            in_array($oldStatus->status_name, $workflowBStatuses)
        ) {
            $this->handleNeedUpdateAction($changeRequest->id);
            return;
        }

        if ($oldStatus && $oldStatus->status_name == 'Pending Create Agreed Scope') {
            if ($newStatus && $newStatus->status_name == 'Request Draft CR Doc') {
                $shouldCreateParallelWorkflows = true;
                $statusesToCreate = [
                    ['status_name' => 'Request Draft CR Doc'],
                    ['status_name' => 'Pending Agreed Scope Approval-SA'],
                    ['status_name' => 'Pending Agreed Scope Approval-Vendor'],
                    ['status_name' => 'Pending Agreed Scope Approval-Business']
                ];
            } elseif ($newStatus && $newStatus->status_name === 'Pending Agreed Scope Approval-SA') {
                $shouldCreateParallelWorkflows = true;
                $statusesToCreate = [
                    ['status_name' => 'Pending Agreed Scope Approval-SA'],
                    ['status_name' => 'Pending Agreed Scope Approval-Vendor'],
                    ['status_name' => 'Pending Agreed Scope Approval-Business']
                ];
            }
        }

        if ($shouldCreateParallelWorkflows && !empty($statusesToCreate)) {
            $previous_group_id = $currentStatus->current_group_id ?? 8;

            foreach ($statusesToCreate as $index => $statusConfig) {
                $statusName = $statusConfig['status_name'];
                $status = Status::where('status_name', $statusName)->first();

                if (!$status) {
                    Log::error('Status not found for parallel workflow', [
                        'status_name' => $statusName,
                        'cr_id' => $changeRequest->id
                    ]);
                    continue;
                }

                $activeStatus = self::ACTIVE_STATUS;

                $current_group_id = $status->GetViewGroup($changeRequest->application_id);
                if ($current_group_id) {
                    $current_group_id = $current_group_id->id;
                } else {
                    $current_group_id = optional($status->group_statuses)
                        ->where('type', '2')
                        ->pluck('group_id')
                        ->first();
                }

                $payload = $this->buildStatusData(
                    $changeRequest->id,
                    $statusData['old_status_id'],
                    $status->id,
                    null,
                    $currentStatus->reference_group_id ?? 8,
                    $previous_group_id,
                    $current_group_id,
                    $userId,
                    $activeStatus
                );

                $this->statusRepository->create($payload);
            }

            $this->active_flag = self::ACTIVE_STATUS;
            return;
        }

        $fixDefectSources = ['Fix Defect-3rd Parties', 'Fix Defect-Vendor'];

        if (
            $oldStatus && in_array($oldStatus->status_name, $fixDefectSources) &&
            $newStatus && $newStatus->status_name === 'IOT In progress'
        ) {
            $previous_group_id = session('current_group') ?: (auth()->check() ? auth()->user()->default_group : null);
            $current_group_id = $newStatus->GetViewGroup($changeRequest->application_id);
            if ($current_group_id) {
                $current_group_id = $current_group_id->id;
            } else {
                $current_group_id = optional($newStatus->group_statuses)
                    ->where('type', '2')
                    ->pluck('group_id')
                    ->first();
            }

            $payload = $this->buildStatusData(
                $changeRequest->id,
                $statusData['old_status_id'],
                $newStatus->id,
                null,
                $current_group_id,
                $previous_group_id,
                $current_group_id,
                $userId,
                self::ACTIVE_STATUS
            );

            $this->statusRepository->create($payload);
            $this->active_flag = self::ACTIVE_STATUS;
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // NORMAL WORKFLOW PROCESSING
        // ═══════════════════════════════════════════════════════════════════

        foreach ($workflow->workflowstatus as $workflowStatus) {

            if ($this->shouldSkipWorkflowStatus($changeRequest, $workflowStatus, $statusData)) {
                continue;
            }

            $active = $this->determineActiveStatus(
                $changeRequest->id,
                $workflowStatus,
                $workflow,
                $statusData['old_status_id'],
                $statusData['new_status_id'],
                $changeRequest
            );

            $newStatusRow = Status::find($workflowStatus->to_status_id);
            $previous_group_id = session('current_group') ?: (auth()->check() ? auth()->user()->default_group : null);
            $viewTechFlag = $newStatusRow?->view_technical_team_flag ?? false;
            if ($changeRequest->workflow_type_id == 15) {
                $viewTechFlag = $newStatusRow?->view_sr_technical_team_flag ?? false;
            }

            if ($viewTechFlag) {
                $previous_technical_teams = [];
                $techRecord = $changeRequest->workflow_type_id == 15 ? $changeRequest->srTechnicalCrFirst : $changeRequest->technical_Cr_first;

                if ($changeRequest && $techRecord) {
                    $previous_technical_teams = $techRecord->technical_cr_team
                        ? $techRecord->technical_cr_team->pluck('group_id')->toArray()
                        : [];
                }

                $teams = $request->technical_teams ?? $request['technical_teams'] ?? null;
                if ($changeRequest->workflow_type_id == 15 && empty($teams)) {
                    $teams = $request->sr_technical_teams ?? $request['sr_technical_teams'] ?? null;
                }
                $teams = $teams ?? $previous_technical_teams;

                if (!empty($teams) && is_iterable($teams)) {
                    foreach ($teams as $teamGroupId) {
                        $payload = $this->buildStatusData(
                            $changeRequest->id,
                            $statusData['old_status_id'],
                            (int) $workflowStatus->to_status_id,
                            (int) $teamGroupId,
                            (int) $teamGroupId,
                            (int) $previous_group_id,
                            (int) $teamGroupId,
                            $userId,
                            $active
                        );

                        $this->statusRepository->create($payload);
                    }
                }
            } else {
                $current_group_id = $newStatusRow->GetViewGroup($changeRequest->application_id);
                if ($current_group_id) {
                    $current_group_id = $current_group_id->id;
                } else {
                    $current_group_id = optional($newStatusRow->group_statuses)
                        ->where('type', '2')
                        ->pluck('group_id')
                        ->first();
                }

                // ════════════════════════════════════════════════════════════
                // ⭐ GROUP OVERRIDE RULE
                // Conditions (ALL must be true):
                //   1. Current status = "Pending Operation DM and Capacity Approval"
                //   2. Workflow type  = "In House"
                //   3. Next status    = "Application Support Production Deployment Pre-requisites"
                // Action: assign group "CR Team Admin" instead of "Application Support"
                // ════════════════════════════════════════════════════════════
                $current_group_id = $this->resolveGroupOverride(
                    $changeRequest,
                    $statusData['old_status_id'],
                    (int) $workflowStatus->to_status_id,
                    $current_group_id
                );

                $payload = $this->buildStatusData(
                    $changeRequest->id,
                    $statusData['old_status_id'],
                    (int) $workflowStatus->to_status_id,
                    null,
                    $current_group_id,
                    $previous_group_id,
                    $current_group_id,
                    $userId,
                    $active
                );

                $this->statusRepository->create($payload);
            }
        }
    }

    private function shouldSkipWorkflowStatus(
        ChangeRequest $changeRequest,
        $workflowStatus,
        array $statusData
    ): bool {
        return $changeRequest->design_duration == '0'
            && $workflowStatus->to_status_id == 40
            && $statusData['old_status_id'] == 74;
    }

    private function determineActiveStatus(
        int $changeRequestId,
        $workflowStatus,
        NewWorkFlow $workflow,
        int $oldStatusId,
        int $newStatusId,
        ChangeRequest $changeRequest
    ): string {

        $fromStatus = Status::find($oldStatusId);
        if ($fromStatus && $fromStatus->status_name === 'Request Draft CR Doc') {
            return self::ACTIVE_STATUS;
        }

        $pendingSaHlStatus = Status::where('status_name', 'Pending SA HL Feedback')->first();

        if ($pendingSaHlStatus && (int) $workflowStatus->to_status_id === (int) $pendingSaHlStatus->id) {
            $agreedScopeStatusIds = array_values(array_filter([
                self::$PENDING_AGREED_SCOPE_SA_STATUS_ID,
                self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID,
                self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID,
            ]));

            if (!empty($agreedScopeStatusIds) && in_array($oldStatusId, $agreedScopeStatusIds, true)) {
                $requiredCount = count($agreedScopeStatusIds);

                $alreadyInserted = ChangeRequestStatus::where('cr_id', $changeRequestId)
                    ->where('new_status_id', $pendingSaHlStatus->id)
                    ->whereIn('old_status_id', $agreedScopeStatusIds)
                    ->count();

                $totalAfterInsert = $alreadyInserted + 1;

                if ($totalAfterInsert >= $requiredCount) {
                    $this->active_flag = self::ACTIVE_STATUS;
                    return self::ACTIVE_STATUS;
                }

                $this->active_flag = self::INACTIVE_STATUS;
                return self::INACTIVE_STATUS;
            }
        }

        $mergePointStatus = Status::where('status_name', 'Pending Update Agreed Requirements')->first();
        $mergePointStatusId = $mergePointStatus ? $mergePointStatus->id : null;

        if ($mergePointStatusId && (int) $workflowStatus->to_status_id === (int) $mergePointStatusId) {
            $usedParallelWorkflows = $this->didUseParallelWorkflows($changeRequestId);

            if ($usedParallelWorkflows) {
                $bothWorkflowsComplete = $this->areBothWorkflowsCompleteById(
                    $changeRequestId,
                    $mergePointStatusId
                );

                if ($bothWorkflowsComplete) {
                    $this->active_flag = self::ACTIVE_STATUS;
                    return self::ACTIVE_STATUS;
                }

                $this->active_flag = self::INACTIVE_STATUS;
                return self::INACTIVE_STATUS;
            }
        }

        $active = self::INACTIVE_STATUS;

        $cr_status = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $oldStatusId)
            ->completedOrActive()
            ->latest()
            ->first();

        if (!$cr_status) {
            $this->active_flag = self::INACTIVE_STATUS;
            return self::INACTIVE_STATUS;
        }

        $parkedIds = array_values(config('change_request.promo_parked_status_ids', []));

        $all_depend_statuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('old_status_id', $cr_status->old_status_id)
            ->completedOrActive()
            ->whereNull('group_id')
            ->whereHas('change_request_data', function ($query) {
                $query->where('workflow_type_id', '!=', 9);
            })
            ->get();

        $depend_statuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('old_status_id', $cr_status->old_status_id)
            ->completed()
            ->whereNull('group_id')
            ->whereHas('change_request_data', function ($query) {
                $query->where('workflow_type_id', '!=', 9);
            })
            ->get();

        $depend_active_statuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('old_status_id', $cr_status->old_status_id)
            ->active()
            ->whereNull('group_id')
            ->whereHas('change_request_data', function ($query) {
                $query->where('workflow_type_id', '!=', 9);
            })
            ->get();

        if ($changeRequest->workflow_type_id == 9) {
            $NextStatusWorkflow = NewWorkFlow::find($newStatusId);

            if ($NextStatusWorkflow && isset($NextStatusWorkflow->workflowstatus[0])) {
                $nextToStatusId = $NextStatusWorkflow->workflowstatus[0]->to_status_id;

                if (in_array($nextToStatusId, $parkedIds, true)) {
                    $depend_active_count = ChangeRequestStatus::where('cr_id', $changeRequestId)
                        ->active()
                        ->count();

                    $active = $depend_active_count > 0 ? self::INACTIVE_STATUS : self::ACTIVE_STATUS;
                } else {
                    $active = self::ACTIVE_STATUS;
                }
            } else {
                $active = self::ACTIVE_STATUS;
            }
        } else {
            $active = $depend_active_statuses->count() > 0 ? self::INACTIVE_STATUS : self::ACTIVE_STATUS;
        }

        $this->active_flag = $active;
        return $active;
    }

    private function didUseParallelWorkflows(int $crId): bool
    {
        $sourceStatus = Status::where('status_name', 'Pending Create Agreed Scope')->first();

        if (!$sourceStatus) {
            return false;
        }

        $workflowAStatusId = Status::where('status_name', 'Request Draft CR Doc')->value('id');

        $workflowBStatusIds = Status::whereIn('status_name', [
            'Pending Agreed Scope Approval-SA',
            'Pending Agreed Scope Approval-Vendor',
            'Pending Agreed Scope Approval-Business'
        ])->pluck('id')->toArray();

        if (!$workflowAStatusId || empty($workflowBStatusIds)) {
            return false;
        }

        $hasWorkflowA = ChangeRequestStatus::where('cr_id', $crId)
            ->where('old_status_id', $sourceStatus->id)
            ->where('new_status_id', $workflowAStatusId)
            ->exists();

        $hasWorkflowB = ChangeRequestStatus::where('cr_id', $crId)
            ->where('old_status_id', $sourceStatus->id)
            ->whereIn('new_status_id', $workflowBStatusIds)
            ->exists();

        return $hasWorkflowA && $hasWorkflowB;
    }

    private function checkWorkflowDependencies(int $changeRequestId, $workflowStatus): bool
    {
        if (!$workflowStatus->dependency_ids) {
            return true;
        }

        $dependencyIds = array_diff(
            $workflowStatus->dependency_ids,
            [$workflowStatus->new_workflow_id]
        );

        foreach ($dependencyIds as $workflowId) {
            if (!$this->isDependencyMet($changeRequestId, $workflowId)) {
                return false;
            }
        }

        return true;
    }

    private function isDependencyMet(int $changeRequestId, int $workflowId): bool
    {
        $dependentWorkflow = NewWorkFlow::find($workflowId);

        if (!$dependentWorkflow) {
            return false;
        }

        return ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $dependentWorkflow->from_status_id)
            ->where('old_status_id', $dependentWorkflow->previous_status_id)
            ->completed()
            ->exists();
    }

    private function checkDependentWorkflows(int $changeRequestId, NewWorkFlow $workflow): string
    {
        $dependentStatuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->active()
            ->get();

        if ($dependentStatuses->count() > 1) {
            return self::INACTIVE_STATUS;
        }

        $checkDependentWorkflow = NewWorkFlow::whereHas('workflowstatus', function ($query) use ($workflow) {
            $query->where('to_status_id', $workflow->workflowstatus[0]->to_status_id);
        })->pluck('from_status_id');

        $dependentCount = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->whereIn('new_status_id', $checkDependentWorkflow)
            ->active()
            ->count();

        return $dependentCount > 0 ? self::INACTIVE_STATUS : self::ACTIVE_STATUS;
    }

    private function buildStatusData(
        int $changeRequestId,
        int $oldStatusId,
        int $newStatusId,
        ?int $group_id,
        ?int $reference_group_id,
        ?int $previous_group_id,
        ?int $current_group_id,
        int $userId,
        string $active
    ): array {
        $status = Status::find($newStatusId);
        $sla = $status ? (int) $status->sla : 0;

        return [
            'cr_id' => $changeRequestId,
            'old_status_id' => $oldStatusId,
            'new_status_id' => $newStatusId,
            'group_id' => $group_id,
            'reference_group_id' => $reference_group_id,
            'previous_group_id' => $previous_group_id,
            'current_group_id' => $current_group_id,
            'user_id' => $userId,
            'sla' => $sla,
            'active' => $active,
        ];
    }

    private function handleNotifications(array $statusData, int $changeRequestId, $request): void
    {
        if (
            $statusData['old_status_id'] == 99 &&
            $this->hasStatusTransition($changeRequestId, 101)
        ) {
            try {
                $this->mailController->notifyCrManager($changeRequestId);
            } catch (Exception $e) {
                Log::error('Failed to send CR Manager notification', [
                    'change_request_id' => $changeRequestId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $newStatusId = NewWorkFlowStatuses::where(
            'new_workflow_id',
            $request->new_status_id
        )->get()->pluck('to_status_id')->toArray();

        $userToNotify = [];
        if (in_array(\App\Services\StatusConfigService::getStatusId('pending_cd_analysis'), $newStatusId)) {
            if (!empty($request->cr_member)) {
                $userToNotify = [$request->cr_member];
            }
        }

        $cr = ChangeRequest::find($changeRequestId);
        $targetStatus = Status::with('group_statuses')->whereIn('id', $newStatusId)->first();
        $viewGroup = GroupStatuses::where('status_id', $targetStatus->id)->where(
            'type',
            '2'
        )->pluck('group_id')->toArray();
        $group_id = $cr->application->group_applications->first()->group_id ?? null;

        $groupToNotify = [];
        if (in_array($group_id, $viewGroup)) {
            $recieveNotification = Group::where('id', $group_id)->where('recieve_notification', '1')->first();
            if ($recieveNotification) {
                $groupToNotify = [$group_id];
            } else {
                $groupToNotify = [];
            }
        } else {
            $groupToNotify = Group::whereIn('id', $viewGroup)
                ->where('recieve_notification', '1')
                ->pluck('id')
                ->toArray();
        }

        if ($this->active_flag == '1' && !empty($groupToNotify)) {
            foreach ($groupToNotify as $groupId) {
                try {
                    $this->mailController->notifyGroup(
                        $changeRequestId,
                        $statusData['old_status_id'],
                        $newStatusId,
                        $groupId,
                        $userToNotify
                    );
                } catch (Exception $e) {
                    Log::error('Failed to send Group notification', [
                        'change_request_id' => $changeRequestId,
                        'group_id' => $groupId,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }
        }
    }

    private function hasStatusTransition(int $changeRequestId, int $toStatusId): bool
    {
        return ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $toStatusId)
            ->exists();
    }

    private function getDependencyService(): CrDependencyService
    {
        if (!$this->dependencyService) {
            $this->dependencyService = new CrDependencyService();
        }

        return $this->dependencyService;
    }

    private function getNeedUpdateWorkflowId(int $changeRequestId, array $statusData): ?int
    {
        try {
            $changeRequest = ChangeRequest::find($changeRequestId);
            if (!$changeRequest) {
                Log::error('Change request not found for workflow lookup', ['cr_id' => $changeRequestId]);
                return null;
            }

            $toStatusId = $this->getStatusIdByName('Pending Create Agreed Scope');
            if (!$toStatusId) {
                Log::error('Target status "Pending Create Agreed Scope" not found', ['cr_id' => $changeRequestId]);
                return null;
            }

            $fromStatusNames = [
                'Pending Agreed Scope Approval-SA',
                'Pending Agreed Scope Approval-Vendor',
                'Pending Agreed Scope Approval-Business',
                'Request Draft CR Doc',
            ];

            $fromStatusIds = [];
            foreach ($fromStatusNames as $statusName) {
                $statusId = $this->getStatusIdByName($statusName);
                $fromStatusIds[] = $statusId;
            }

            $fromStatusIds = array_filter($fromStatusIds);
            if (empty($fromStatusIds)) {
                Log::error('No valid source statuses found for Need Update transition', ['cr_id' => $changeRequestId]);
                return null;
            }

            foreach ($fromStatusIds as $fromStatusId) {
                $workflowId = $this->getWorkflowIdByStatusTransition(
                    $changeRequest->workflow_type_id,
                    $fromStatusId,
                    $toStatusId
                );

                if ($workflowId) {
                    return $workflowId;
                }
            }

            Log::warning('No workflow found for Need Update transition', [
                'cr_id' => $changeRequestId,
                'workflow_type_id' => $changeRequest->workflow_type_id,
                'from_status_ids' => $fromStatusIds,
                'to_status_id' => $toStatusId
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Error getting Need Update workflow ID', [
                'cr_id' => $changeRequestId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getWorkflowIdByStatusTransition(int $workflow_type_id, int $from_status_id, int $to_status_id): ?int
    {
        return \App\Models\NewWorkFlow::query()
            ->select('new_workflow.id')
            ->join('new_workflow_statuses as nws', 'nws.new_workflow_id', '=', 'new_workflow.id')
            ->where('new_workflow.type_id', $workflow_type_id)
            ->where('new_workflow.from_status_id', $from_status_id)
            ->where('nws.to_status_id', $to_status_id)
            ->orderBy('new_workflow.id', 'desc')
            ->value('new_workflow.id');
    }

    private function getStatusIdByName(string $statusName): ?int
    {
        $status = Status::where('status_name', $statusName)
            ->where('active', '1')
            ->first();

        return $status ? $status->id : null;
    }

    private function isNeedUpdateTransition(int $crId, array $statusData): bool
    {
        $newStatusId = $statusData['new_status_id'] ?? null;
        $needUpdateWorkflowId = $this->getNeedUpdateWorkflowId($crId, $statusData);
        return $newStatusId === $needUpdateWorkflowId;
    }

    private function handleAgreedScopeApprovalTransition(int $crId, array $statusData): void
    {
        $parallelWorkflowStatusIds = [
            self::$PENDING_AGREED_SCOPE_SA_STATUS_ID,
            self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID,
            self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID,
            self::$REQUEST_DRAFT_CR_DOC_STATUS_ID,
        ];

        $parallelWorkflowStatusIds = array_filter($parallelWorkflowStatusIds);

        if (empty($parallelWorkflowStatusIds)) {
            Log::error('No parallel workflow status IDs configured', ['cr_id' => $crId]);
            throw new Exception('Parallel workflow status IDs not configured');
        }

        $archivedCount = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('new_status_id', $parallelWorkflowStatusIds)
            ->whereIn('active', ['0', '1'])
            ->update(['active' => self::COMPLETED_STATUS]);

        $lastArchivedRecord = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::COMPLETED_STATUS)
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastArchivedRecord) {
            Log::error('No archived record found to reinsert', ['cr_id' => $crId]);
            throw new Exception("No archived record found for CR {$crId}");
        }

        $otherActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS)
            ->update(['active' => self::COMPLETED_STATUS]);

        $newActiveRecord = $lastArchivedRecord->replicate();
        $newActiveRecord->active = self::ACTIVE_STATUS;
        $newActiveRecord->created_at = now();
        $newActiveRecord->updated_at = null;
        $newActiveRecord->save();
    }

    public function handleNeedUpdateAction(int $crId): bool
    {
        try {
            DB::beginTransaction();

            $changeRequest = ChangeRequest::find($crId);
            if (!$changeRequest) {
                throw new Exception("Change Request not found: {$crId}");
            }

            $parallelStatusNames = [
                'Pending Agreed Scope Approval-SA',
                'Pending Agreed Scope Approval-Vendor',
                'Pending Agreed Scope Approval-Business',
                'Request Draft CR Doc'
            ];

            $parallelStatusIds = [];
            foreach ($parallelStatusNames as $statusName) {
                $statusId = $this->getStatusIdByName($statusName);
                if ($statusId) {
                    $parallelStatusIds[] = $statusId;
                }
            }

            if (empty($parallelStatusIds)) {
                Log::warning('No parallel status IDs found', [
                    'cr_id' => $crId,
                    'status_names' => $parallelStatusNames
                ]);
                DB::rollBack();
                return false;
            }

            $deactivatedCount = ChangeRequestStatus::where('cr_id', $crId)
                ->whereIn('new_status_id', $parallelStatusIds)
                ->active()
                ->update(['active' => self::INACTIVE_STATUS]);

            if ($deactivatedCount === 0) {
                DB::commit();
                return false;
            }

            $latestStatusRecord = ChangeRequestStatus::where('cr_id', $crId)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$latestStatusRecord) {
                throw new Exception("No status records found for CR: {$crId}");
            }

            $duplicatedRecord = $latestStatusRecord->replicate();
            $duplicatedRecord->active = self::ACTIVE_STATUS;
            $duplicatedRecord->created_at = now();
            $duplicatedRecord->updated_at = null;

            if (!$duplicatedRecord->created_at) {
                $duplicatedRecord->created_at = now();
            }

            $insertData = $duplicatedRecord->toArray();
            unset($insertData['id']);

            $newRecordId = DB::table('change_request_statuses')->insertGetId($insertData);

            DB::commit();

            $cleanupCount = ChangeRequestStatus::where('cr_id', $crId)
                ->whereIn('new_status_id', $parallelStatusIds)
                ->where('active', '1')
                ->where('id', '>', $newRecordId)
                ->update(['active' => self::INACTIVE_STATUS]);

            $sameTransactionCleanup = ChangeRequestStatus::where('cr_id', $crId)
                ->whereIn('new_status_id', $parallelStatusIds)
                ->where('active', '1')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->where('id', '!=', $newRecordId)
                ->update(['active' => self::COMPLETED_STATUS]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing Need Update action', [
                'cr_id' => $crId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function isTransitionFromPendingCab(ChangeRequest $changeRequest, array $statusData): bool
    {
        if (self::$PENDING_CAB_STATUS_ID === null) {
            Log::info('Pending CAB status ID is null', [
                'cr_id' => $changeRequest->id,
                'status_data' => $statusData,
            ]);
            return false;
        }

        $workflow = NewWorkFlow::where('from_status_id', self::$PENDING_CAB_STATUS_ID)
            ->where('type_id', $changeRequest->workflow_type_id)
            ->where('workflow_type', '0')
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->first();

        if (!$workflow) {
            Log::info('Workflow not found for transition from Pending CAB', [
                'cr_id' => $changeRequest->id,
                'status_data' => $statusData,
            ]);
            return false;
        }

        Log::info('Workflow found for transition from Pending CAB', [
            'cr_id' => $changeRequest->id,
            'status_data' => $statusData,
            'workflow' => $workflow,
        ]);

        return isset($statusData['new_status_id']) &&
            (int) $statusData['new_status_id'] === $workflow->id;
    }

    private function checkAndFireDeliveredEvent(ChangeRequest $changeRequest, array $statusData): void
    {
        $newWorkflowId = $statusData['new_status_id'] ?? null;
        if (!$newWorkflowId) {
            return;
        }

        $workflow = NewWorkFlow::with('workflowstatus')->find($newWorkflowId);
        if (!$workflow) {
            return;
        }

        foreach ($workflow->workflowstatus as $wfStatus) {
            if (in_array((int) $wfStatus->to_status_id, [self::$DELIVERED_STATUS_ID, self::$REJECTED_STATUS_ID], true)) {
                $changeRequest->refresh();
                event(new CrDeliveredEvent($changeRequest));
                return;
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // ⭐ GROUP OVERRIDE HELPER
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Override the resolved group when ALL of these conditions are true:
     *
     *   1. Current status  = "Pending Operation DM and Capacity Approval"
     *   2. Workflow type   = "In House"
     *   3. Next status     = "Application Support Production Deployment Pre-requisites"
     *
     * When matched: returns the "CR Team Admin" group ID.
     * Otherwise:    returns $defaultGroupId unchanged.
     */
    private function resolveGroupOverride(
        ChangeRequest $changeRequest,
        int $currentStatusId,
        int $nextStatusId,
        ?int $defaultGroupId
    ): ?int {
        try {
            // 1. Current status must be "Pending Operation DM and Capacity Approval"
            $currentStatusName = Status::where('id', $currentStatusId)->value('status_name');
            if ($currentStatusName !== 'Pending Operation DM and Capacity Approval') {
                return $defaultGroupId;
            }

            // 2. Workflow type must be "In House"
            $workflowTypeName = DB::table('workflow_type')
                ->where('id', $changeRequest->workflow_type_id)
                ->value('name');
            if ($workflowTypeName !== 'In House') {
                return $defaultGroupId;
            }

            // 3. Next status must be "Application Support Production Deployment Pre-requisites"
            $nextStatusName = Status::where('id', $nextStatusId)->value('status_name');
            if ($nextStatusName !== 'Application Support Production Deployment Pre-requisites') {
                return $defaultGroupId;
            }

            // 4. All conditions met — look up "CR Team Admin" group ID
            $crTeamAdminGroupId = DB::table('groups')->where('name', 'CR Team Admin')->value('id');
            if (!$crTeamAdminGroupId) {
                Log::error('[GroupOverride] Group "CR Team Admin" not found in groups table — keeping default group.');
                return $defaultGroupId;
            }

            Log::info('[GroupOverride] Assigning "CR Team Admin" (id=' . $crTeamAdminGroupId . ') for CR #' . $changeRequest->id);
            return (int) $crTeamAdminGroupId;

        } catch (\Throwable $e) {
            Log::error('[GroupOverride] Unexpected error: ' . $e->getMessage(), ['cr_id' => $changeRequest->id]);
            return $defaultGroupId;
        }
    }
}
