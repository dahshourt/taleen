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
    // private const PENDING_CAB_STATUS_ID = 38;
    // private const DELIVERED_STATUS_ID = 27;
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

        // Initialize agreed scope approval status IDs
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
    /**
     * Check if both workflows have reached the merge point status
     * 
     * @param int $crId
     * @param string $mergeStatusName The merge point status name (e.g., "Pending Update Agreed Requirements")
     * @return bool True if both workflows have reached this status
     */

    /**
     * Get workflow ID for transitioning from ATP Review statuses to Request Update ATPs
     */
    private function getRequestUpdateAtpsWorkflowId(int $changeRequestId): ?int
    {
        // Get current status of the CR
        $changeRequest = ChangeRequest::find($changeRequestId);
        if (!$changeRequest) {
            return null;
        }

        $currentStatusId = $changeRequest->status_id;

        // Check if current status is one of the ATP Review statuses
        if (!in_array($currentStatusId, [self::$ATP_REVIEW_QC_STATUS_ID, self::$ATP_REVIEW_UAT_STATUS_ID])) {
            return null;
        }

        // Find workflow from current ATP Review status to "Request Update ATPs"
        $workflow = NewWorkFlow::where('from_status_id', $currentStatusId)
            ->whereHas('workflowstatus', function ($query) {
                $query->where('to_status_id', self::$REQUEST_UPDATE_ATPS_STATUS_ID);
            })
            ->active()
            ->first();

        return $workflow?->id;
    }

    /**
     * Handle transition from ATP Review (QC/UAT) to Request Update ATPs
     * Sets the other ATP Review status to active=2
     */
    private function handleRequestUpdateAtpsTransition(int $changeRequestId, array $statusData): void
    {

        $changeRequest = ChangeRequest::findOrFail($changeRequestId);
        $currentStatusId = $changeRequest->status_id;

        // Determine which ATP Review status to deactivate (the one we're NOT coming from)
        $statusToDeactivate = null;

        if ($currentStatusId == self::$ATP_REVIEW_QC_STATUS_ID) {
            // Coming from QC, so deactivate UAT if it exists
            $statusToDeactivate = self::$ATP_REVIEW_UAT_STATUS_ID;
        } elseif ($currentStatusId == self::$ATP_REVIEW_UAT_STATUS_ID) {
            // Coming from UAT, so deactivate QC if it exists
            $statusToDeactivate = self::$ATP_REVIEW_QC_STATUS_ID;
        }

        // Update the other ATP Review status to active=2
        if ($statusToDeactivate) {
            $updated = ChangeRequestStatus::where('change_request_id', $changeRequestId)
                ->where('status_id', $statusToDeactivate)
                ->where('active', 1) // Only update if currently active
                ->update(['active' => 2]);

            if ($updated > 0) {
            } else {
            }
        }

        // Now create the new "Request Update ATPs" status record
        $newStatusRecord = ChangeRequestStatus::create([
            'change_request_id' => $changeRequestId,
            'status_id' => self::$REQUEST_UPDATE_ATPS_STATUS_ID,
            'user_id' => $statusData['user_id'] ?? auth()->id(),
            'active' => 1,
            'comment' => $statusData['comment'] ?? 'Transition to Request Update ATPs',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update the change request main status
        $changeRequest->update([
            'status_id' => self::$REQUEST_UPDATE_ATPS_STATUS_ID,
            'updated_at' => now(),
        ]);

    }
    private function haveBothWorkflowsReachedMergePoint(int $crId, string $mergeStatusName): bool
    {
        // Find the merge status ID
        $mergeStatus = Status::where('name', $mergeStatusName)->first();

        if (!$mergeStatus) {
            Log::error('Merge status not found', [
                'status_name' => $mergeStatusName
            ]);
            return false;
        }

        // Get all status records for this CR with the merge status
        $mergeStatusRecords = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $mergeStatus->id)
            ->get();

        if ($mergeStatusRecords->isEmpty()) {
            return false;
        }

        // Count how many unique old_status_ids have reached the merge point
        // We need at least 2: one from Workflow A and one from Workflow B
        $uniqueSourceStatuses = $mergeStatusRecords->pluck('old_status_id')->unique();


        // Both workflows have reached if we have records from 2+ different source statuses
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
    /**
     * Check if BOTH workflows have reached merge point
     * Works by counting how many different paths led to the merge status
     */
    /**
     * Check if both workflows reached merge point
     * Dynamically determines workflow paths
     */
    private function areBothWorkflowsCompleteById(int $crId, int $mergeStatusId): bool
    {

        // Get all records where new_status_id = merge point (250)
        $mergeRecords = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $mergeStatusId)
            ->get();

        if ($mergeRecords->isEmpty()) {
            return false;
        }

        // Count unique old_status_id values (different workflow paths)
        $uniquePaths = $mergeRecords->pluck('old_status_id')->unique();
        $pathCount = $uniquePaths->count();


        // Need at least 2 different paths (Workflow A + Workflow B)
        return $pathCount >= 2;
    }
    private function activatePendingMergeStatus(int $crId, array $statusData): void
    {

        $mergeStatusName = 'Pending Update Agreed Requirements';
        $mergeStatus = Status::where('status_name', $mergeStatusName)->first();
        $mergePointStatusId = $mergeStatus ? $mergeStatus->id : null;

        // Only if we just reached the merge point
        if ($statusData['new_status_id'] == $mergePointStatusId) {

            if ($this->areBothWorkflowsCompleteById($crId, $mergePointStatusId)) {


                // Find records with active=0 from merge point
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

    /**
     * Check if both workflows have reached merge point by checking specific workflow statuses
     * More accurate version that checks specific workflow completion
     */
    // private function areBothWorkflowsComplete(int $crId): bool
// {
//     $mergeStatusName = 'Pending Update Agreed Requirements';
//     $mergeStatus = Status::where('status_name', $mergeStatusName)->first();

    //     if (!$mergeStatus) {
//         return false;
//     }

    //     // Define what we're looking for:
//     // Workflow A source: "Request Draft CR Doc" or its next statuses
//     // Workflow B source: One of the three approval statuses or their next statuses

    //     $workflowAStatuses = [
//         'Request Draft CR Doc',
//         'Pending Update Draft CR Doc',
//         // Add other Workflow A intermediate statuses here
//     ];

    //     $workflowBStatuses = [
//         'Pending Agreed Scope Approval-SA',
//         'Pending Agreed Scope Approval-Vendor',
//         'Pending Agreed Scope Approval-Business',
//         // Add other Workflow B intermediate statuses here
//     ];

    //     // Check if Workflow A has reached the merge point
//     $workflowAStatusIds = Status::whereIn('status_name', $workflowAStatuses)->pluck('id');
//     $workflowAReached = ChangeRequestStatus::where('cr_id', $crId)
//         ->whereIn('old_status_id', $workflowAStatusIds)
//         ->where('new_status_id', $mergeStatus->id)
//         ->exists();

    //     // Check if Workflow B has reached the merge point
//     $workflowBStatusIds = Status::whereIn('status_name', $workflowBStatuses)->pluck('id');
//     $workflowBReached = ChangeRequestStatus::where('cr_id', $crId)
//         ->whereIn('old_status_id', $workflowBStatusIds)
//         ->where('new_status_id', $mergeStatus->id)
//         ->exists();

    //     Log::info('Checking both workflows completion', [
//         'cr_id' => $crId,
//         'workflow_a_reached' => $workflowAReached,
//         'workflow_b_reached' => $workflowBReached,
//         'both_complete' => $workflowAReached && $workflowBReached
//     ]);

    //     return $workflowAReached && $workflowBReached;
// }


    private function requiresMergePointCheck(int $fromStatusId, int $toStatusId): bool
    {
        // Check if there's a workflow with same_time = 1
        $workflowStatus = \App\Models\NewWorkflowStatus::where('from_status_id', $fromStatusId)
            ->where('to_status_id', $toStatusId)
            ->first();

        if (!$workflowStatus || !$workflowStatus->workflow) {
            return false;
        }

        // If same_time = 1, this transition requires merge point check
        return $workflowStatus->workflow->same_time == 1;
    }
    // public function updateChangeRequestStatus(int $changeRequestId, $request): bool
    // {
    //     try {
    //         DB::beginTransaction();

    //         $statusData = $this->extractStatusData($request);
    //         $workflow = $this->getWorkflow($statusData);
    //         $changeRequest = $this->getChangeRequest($changeRequestId);
    //         $userId = $this->getUserId($changeRequest, $request);

    //         Log::info('ChangeRequestStatusService: updateChangeRequestStatus', [
    //             'changeRequestId' => $changeRequestId,
    //             'statusData' => $statusData,
    //             'workflow' => $workflow,
    //             'changeRequest' => $changeRequest,
    //             'userId' => $userId,
    //         ]);

    //         if (!$workflow) {
    //             $newStatusId = $statusData['new_status_id'] ?? 'not set';
    //             throw new Exception("Workflow not found for status: {$newStatusId}");
    //         }

    //         // Check if status has changed
    //         $statusChanged = $this->validateStatusChange($changeRequest, $statusData, $workflow);

    //         // If status hasn't changed, just return true without throwing an error
    //         if (!$statusChanged) {
    //             DB::commit();

    //             return true;
    //         }

    //         // Check for dependency hold when transitioning from Pending CAB to pending design
    //         if ($this->isTransitionFromPendingCab($changeRequest, $statusData)) {
    //             $depService = $this->getDependencyService();
    //             if ($depService->shouldHoldCr($changeRequestId)) {
    //                 // Apply dependency hold instead of transitioning
    //                 $depService->applyDependencyHold($changeRequestId);
    //                 Log::info('CR held due to unresolved dependencies', [
    //                     'cr_id' => $changeRequestId,
    //                     'cr_no' => $changeRequest->cr_no,
    //                 ]);
    //                 DB::commit();

    //                 return true; // Block the transition
    //             }
    //         }

    //         $this->processStatusUpdate($changeRequest, $statusData, $workflow, $userId, $request);

    //         // Fire CrDeliveredEvent if CR reached Delivered status
    //         //$this->checkAndFireDeliveredEvent($changeRequest, $statusData);

    //         DB::commit();
    //         // Fire CrDeliveredEvent if CR reached Delivered status
    //         $this->checkAndFireDeliveredEvent($changeRequest, $statusData);

    //         return true;

    //     } catch (Exception $e) {
    //         DB::rollback();
    //         Log::error('Error updating change request status', [
    //             'change_request_id' => $changeRequestId,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);
    //         throw $e;
    //     }
    // }

    // public function updateChangeRequestStatus(int $changeRequestId, $request): bool
    // {
    //     Log::info('updateChangeRequestStatus called', [
    //         'change_request_id' => $changeRequestId,
    //         'request_data' => $request->all()
    //     ]);

    //     try {
    //         DB::beginTransaction();

    //         $statusData = $this->extractStatusData($request);
    //         $workflow = $this->getWorkflow($statusData);
    //         $changeRequest = $this->getChangeRequest($changeRequestId);
    //         $userId = $this->getUserId($changeRequest, $request);

    //         // Process update - determineActiveStatus handles merge point logic
    //         $this->processStatusUpdate($changeRequest, $statusData, $workflow, $userId, $request);

    //         // Handle agreed scope approval transition logic
    //         Log::info('About to call handleAgreedScopeApprovalTransition', [
    //             'cr_id' => $changeRequest->id,
    //             'status_data' => $statusData,
    //             'new_status_id' => $statusData['new_status_id'] ?? 'null'
    //         ]);
    //         $this->handleAgreedScopeApprovalTransition($changeRequest->id, $statusData);

    //         // Activate pending statuses if needed
    //         $this->activatePendingMergeStatus($changeRequest->id, $statusData);

    //         DB::commit();
    //         // Fire CrDeliveredEvent if CR reached Delivered status
    //         $this->checkAndFireDeliveredEvent($changeRequest, $statusData);

    //         event(new ChangeRequestStatusUpdated($changeRequest, $statusData, $request, $this->active_flag));

    //         return true;

    //     } catch (Exception $e) {
    //         DB::rollback();
    //         Log::error('Error updating change request status', [
    //             'change_request_id' => $changeRequestId,
    //             'error' => $e->getMessage()
    //         ]);
    //         throw $e;
    //     }
    // }

    public function updateChangeRequestStatus(int $changeRequestId, $request): bool
    {

        try {
            DB::beginTransaction();

            $statusData = $this->extractStatusData($request);

            // ════════════════════════════════════════════════════════════════
            // ⭐⭐⭐ CRITICAL FIX: Early check for "Need Update" (dynamic workflow ID)
            // This MUST run BEFORE processStatusUpdate to prevent unwanted records
            // ════════════════════════════════════════════════════════════════

            // Get the workflow ID dynamically for "Need Update" transition
            $needUpdateWorkflowIds = $this->getAllNeedUpdateWorkflowIds($changeRequestId);


            if (isset($statusData['new_status_id']) && !empty($needUpdateWorkflowIds) && in_array($statusData['new_status_id'], $needUpdateWorkflowIds)) {

                // Handle the special "Need Update" logic
                $this->handleNeedUpdateTransition($changeRequestId, $statusData);

                DB::commit();


                return true; // ⭐ EXIT EARLY - don't run normal workflow
            }

            // ════════════════════════════════════════════════════════════════
            // ⭐⭐⭐ NEW: Check for "Request Update ATPs" transition from ATP Review statuses
            // ════════════════════════════════════════════════════════════════

            $requestUpdateAtpsWorkflowId = $this->getRequestUpdateAtpsWorkflowId($changeRequestId);

            if (
                isset($statusData['new_status_id']) &&
                $statusData['new_status_id'] == $requestUpdateAtpsWorkflowId &&
                self::$REQUEST_UPDATE_ATPS_STATUS_ID !== null &&
                $statusData['new_status_id'] == self::$REQUEST_UPDATE_ATPS_STATUS_ID
            ) {


                // Handle the special "Request Update ATPs" logic
                $this->handleRequestUpdateAtpsTransition($changeRequestId, $statusData);

                DB::commit();


                return true; // ⭐ EXIT EARLY - don't run normal workflow
            }
            try {
                $iotService = new \App\Services\ChangeRequest\SpecialFlows\IotTcsFlowService();

                if ($iotService->isIotTcsTransition($changeRequestId, $statusData)) {

                    $changeRequest = $this->getChangeRequest($changeRequestId);

                    // Build context for group/user resolution
                    $context = [
                        'user_id' => Auth::id() ?? null,
                        'application_id' => $changeRequest->application_id ?? null,
                    ];

                    $activeFlag = $iotService->handleIotTcsTransition($changeRequestId, $statusData, $context);
                    $this->active_flag = $activeFlag;

                    DB::commit();


                    event(new ChangeRequestStatusUpdated($changeRequest, $statusData, $request, $this->active_flag));

                    return true; // ⭐ EXIT EARLY – normal workflow must NOT run
                }
            } catch (\Throwable $e) {
                Log::error('Error in IotTcsFlowService check', [
                    'cr_id' => $changeRequestId,
                    'error' => $e->getMessage(),
                ]);
                // Fall through to normal workflow on service error
            }

            // ════════════════════════════════════════════════════════════════
            // END OF REQUEST UPDATE ATPs FIX
            // ════════════════════════════════════════════════════════════════

            // Normal workflow continues only if NOT a special transition
            $changeRequest = $this->getChangeRequest($changeRequestId);

            // SPECIAL TRANSITION FOR APP SUPPORT == 1 (Production Deployment In-Progress -> Delivered)
            $deliveredStatusId = \App\Services\StatusConfigService::getStatusId('Delivered');
            $productionDeploymentInProgressId = \App\Services\StatusConfigService::getStatusId('production_deployment_in_progress');
            // Resolve In-House workflow type IDs dynamically from DB by name (no hardcoded IDs)
            $inHouseWorkflowTypeIds = DB::table('workflow_type')->where('name', 'In House')->pluck('id')->toArray();
            if (isset($statusData['new_status_id']) && $statusData['new_status_id'] == $deliveredStatusId && $statusData['old_status_id'] == $productionDeploymentInProgressId) {
                if (
                    $changeRequest &&
                    $changeRequest->application &&
                    $changeRequest->application->app_support == 1 &&
                    $changeRequest->workflow_type_id != 9
                ) { // Not promo workflow (in-house)

                    // Deactivate current active statuses
                    ChangeRequestStatus::where('cr_id', $changeRequestId)
                        ->where('active', self::ACTIVE_STATUS)
                        ->update(['active' => self::COMPLETED_STATUS]);

                    $lastRecord = ChangeRequestStatus::where('cr_id', $changeRequestId)
                        ->where('active', self::COMPLETED_STATUS)
                        ->orderBy('id', 'desc')
                        ->first();

                    // Create new active status for Delivered
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

                    // Fire CrDeliveredEvent if CR reached Delivered status
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
                    // Apply dependency hold instead of transitioning
                    $depService->applyDependencyHold($changeRequestId);
                    Log::info('Dependency hold applied', [
                        'cr_id' => $changeRequestId,
                        'status_data' => $statusData,
                    ]);
                    DB::commit();

                    return true; // Block the transition
                }
            }

            // Process update - determineActiveStatus handles merge point logic
            $this->processStatusUpdate($changeRequest, $statusData, $workflow, $userId, $request);

            $this->activatePendingMergeStatus($changeRequest->id, $statusData);

            DB::commit();

            // Fire CrDeliveredEvent if CR reached Delivered status
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

        // ════════════════════════════════════════════════════════════════════
        // STEP 1: Archive ALL active records first (active: 1 → 2)
        // THIS MUST HAPPEN BEFORE EVERYTHING ELSE
        // ════════════════════════════════════════════════════════════════════

        $allActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS) // active = '1'
            ->update(['active' => self::COMPLETED_STATUS]); // active = '2'


        // ════════════════════════════════════════════════════════════════════
        // STEP 2: Define the 4 parallel workflow status IDs to archive
        // ════════════════════════════════════════════════════════════════════

        // These are the status IDs for the 4 parallel workflows:
        // - Pending Agreed Scope Approval-SA (e.g., 292)
        // - Pending Agreed Scope Approval-Vendor (e.g., 293)
        // - Pending Agreed Scope Approval-Business (e.g., 294)
        // - Request Draft CR Doc (e.g., 295)
        $parallelStatusIds = [
            self::$PENDING_AGREED_SCOPE_SA_STATUS_ID,
            self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID,
            self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID,
            self::$REQUEST_DRAFT_CR_DOC_STATUS_ID,
        ];

        // Filter out any null values (in case status not found)
        $parallelStatusIds = array_filter($parallelStatusIds);

        // Validate that we have status IDs configured
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


        // ════════════════════════════════════════════════════════════════════
        // STEP 3: Archive parallel workflow records (active: 0 or 1 → 2)
        // (This catches any that weren't caught in Step 1)
        // ════════════════════════════════════════════════════════════════════

        // This archives BOTH active (1) and inactive (0) records
        // All should become archived (2)
        $archivedCount = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('new_status_id', $parallelStatusIds)
            ->whereIn('active', ['0', '1'])
            ->update(['active' => self::COMPLETED_STATUS]); // active = '2'


        // Validate that we archived records
        if ($archivedCount === 0) {
            Log::warning('No parallel workflow records found to archive', [
                'cr_id' => $crId,
                'searched_status_ids' => $parallelStatusIds
            ]);
        }

        // ════════════════════════════════════════════════════════════════════
        // STEP 4: Get the LAST archived record (highest ID where active=2)
        // ════════════════════════════════════════════════════════════════════

        $lastRecord = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::COMPLETED_STATUS) // active = '2'
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastRecord) {
            Log::error('No archived record found to use as template', [
                'cr_id' => $crId,
                'active_status_checked' => self::COMPLETED_STATUS
            ]);
            throw new Exception("No archived record found for CR {$crId}");
        }


        // ════════════════════════════════════════════════════════════════════
        // STEP 5: VERIFY no active records exist (safety check)
        // ════════════════════════════════════════════════════════════════════

        $stillActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS)
            ->count();

        if ($stillActiveCount > 0) {
            Log::warning('Found active records that should have been archived', [
                'cr_id' => $crId,
                'count' => $stillActiveCount
            ]);

            // Archive them now (safety measure)
            ChangeRequestStatus::where('cr_id', $crId)
                ->where('active', self::ACTIVE_STATUS)
                ->update(['active' => self::COMPLETED_STATUS]);

        } else {
        }


        $newRecord = $lastRecord->replicate();

        // ⭐ OVERRIDE new_status_id to 291 (Pending Create Agreed Scope)
        // This is the "Need Update" destination status
        $newRecord->new_status_id = self::$PENDING_CREATE_AGREED_SCOPE_STATUS_ID; // 291

        // Set old_status_id to the status we're coming FROM
        $newRecord->old_status_id = $lastRecord->new_status_id;

        // Set it as active - THIS IS THE ONLY RECORD WITH active='1'
        $newRecord->active = self::ACTIVE_STATUS; // '1'

        // Update timestamps
        $newRecord->created_at = now();
        $newRecord->updated_at = null;

        // Save to database (gets new auto-increment ID)
        $newRecord->save();


        // ════════════════════════════════════════════════════════════════════
        // STEP 7: FINAL VERIFICATION - Ensure only 1 active record
        // ════════════════════════════════════════════════════════════════════

        $finalActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS)
            ->count();

        if ($finalActiveCount !== 1) {
            Log::error('CRITICAL: Expected 1 active record but found ' . $finalActiveCount, [
                'cr_id' => $crId,
                'active_count' => $finalActiveCount
            ]);
        } else {
        }

        // ════════════════════════════════════════════════════════════════════
        // FINAL: Log completion
        // ════════════════════════════════════════════════════════════════════

    }


    /**
     * Check if both workflows reached merge point using status NAMES
     */
    private function areBothWorkflowsComplete(int $crId): bool
    {
        // IMPORTANT: Replace 'name' with your actual column name throughout

        $mergeStatusName = 'Pending Update Agreed Requirements';

        // Find merge status
        $mergeStatus = Status::where('status_name', $mergeStatusName)->first();  // ← Change 'name' if needed

        if (!$mergeStatus) {
            Log::error('Merge status not found', [
                'status_name' => $mergeStatusName
            ]);
            return false;
        }

        // Workflow A status names
        $workflowANames = [
            'Request Draft CR Doc',
            'Pending Update Draft CR Doc',
        ];

        // Get Workflow A status IDs
        $workflowAStatusIds = Status::whereIn('status_name', $workflowANames)  // ← Change 'name' if needed
            ->pluck('id')
            ->toArray();


        // Check if Workflow A reached merge point
        $workflowAReached = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('old_status_id', $workflowAStatusIds)
            ->where('new_status_id', $mergeStatus->id)
            ->exists();


        // Workflow B status names
        $workflowBNames = [
            'Pending Agreed Scope Approval-SA',
            'Pending Agreed Scope Approval-Vendor',
            'Pending Agreed Scope Approval-Business',
        ];

        // Get Workflow B status IDs
        $workflowBStatusIds = Status::whereIn('status_name', $workflowBNames)  // ← Change 'name' if needed
            ->pluck('id')
            ->toArray();


        // Check if Workflow B reached merge point
        $workflowBReached = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('old_status_id', $workflowBStatusIds)
            ->where('new_status_id', $mergeStatus->id)
            ->exists();


        $bothComplete = $workflowAReached && $workflowBReached;


        return $bothComplete;
    }
    // public function updateChangeRequestStatus(int $changeRequestId, $request): bool
    // {
    //     try {
    //         DB::beginTransaction();

    //         $statusData = $this->extractStatusData($request);
    //         $workflow = $this->getWorkflow($statusData);
    //         $changeRequest = $this->getChangeRequest($changeRequestId);
    //         $userId = $this->getUserId($changeRequest, $request);

    //         Log::info('ChangeRequestStatusService: updateChangeRequestStatus', [
    //             'changeRequestId' => $changeRequestId,
    //             'statusData' => $statusData,
    //             'workflow' => $workflow,
    //             'changeRequest' => $changeRequest,
    //             'userId' => $userId,
    //         ]);
    //     $fromStatus = Status::find($statusData['old_status_id']);
    //     $toStatus = Status::find($statusData['new_status_id']);

    //     // Check if transitioning FROM merge point TO next status
    //     if ($fromStatus && $fromStatus->name === 'Pending Update Agreed Requirements') {
    //         // Check if trying to proceed to "Pending Receive Vendor CR Doc"
    //         if ($toStatus && $toStatus->name === 'Pending Receive Vendor CR Doc') {

    //             // Check if both workflows have reached the merge point
    //             if (!$this->areBothWorkflowsComplete($changeRequestId)) {

    //                 Log::warning('Transition blocked - both workflows have not reached merge point', [
    //                     'cr_id' => $changeRequestId,
    //                     'from_status' => $fromStatus->name,
    //                     'to_status' => $toStatus->name
    //                 ]);

    //                 DB::rollBack();

    //                 throw new \Exception(
    //                     'Cannot proceed to "Pending Receive Vendor CR Doc". ' .
    //                     'Both Workflow A and Workflow B must reach "Pending Update Agreed Requirements" first. ' .
    //                     'Please ensure both workflows are completed before proceeding.'
    //                 );
    //             }

    //             Log::info('Merge point check passed - both workflows complete', [
    //                 'cr_id' => $changeRequestId,
    //                 'proceeding_to' => $toStatus->name
    //             ]);
    //         }
    //     }

    //         if (!$workflow) {
    //             $newStatusId = $statusData['new_status_id'] ?? 'not set';
    //             throw new Exception("Workflow not found for status: {$newStatusId}");
    //         }

    //         // Check if status has changed
    //         $statusChanged = $this->validateStatusChange($changeRequest, $statusData, $workflow);

    //         // If status hasn't changed, just return true without throwing an error
    //         if (!$statusChanged) {
    //             DB::commit();

    //             return true;
    //         }

    //         // Check for dependency hold when transitioning from Pending CAB to pending design
    //         if ($this->isTransitionFromPendingCab($changeRequest, $statusData)) {
    //             $depService = $this->getDependencyService();
    //             if ($depService->shouldHoldCr($changeRequestId)) {
    //                 // Apply dependency hold instead of transitioning
    //                 $depService->applyDependencyHold($changeRequestId);
    //                 Log::info('CR held due to unresolved dependencies', [
    //                     'cr_id' => $changeRequestId,
    //                     'cr_no' => $changeRequest->cr_no,
    //                 ]);
    //                 DB::commit();

    //                 return true; // Block the transition
    //             }
    //         }

    //         $this->processStatusUpdate($changeRequest, $statusData, $workflow, $userId, $request);

    //         // Fire CrDeliveredEvent if CR reached Delivered status
    //         //$this->checkAndFireDeliveredEvent($changeRequest, $statusData);

    //         DB::commit();
    //         // Fire CrDeliveredEvent if CR reached Delivered status
    //         $this->checkAndFireDeliveredEvent($changeRequest, $statusData);

    //         return true;

    //     } catch (Exception $e) {
    //         DB::rollback();
    //         Log::error('Error updating change request status', [
    //             'change_request_id' => $changeRequestId,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);
    //         throw $e;
    //     }
    // }

    /**
     * Check if a status is the independent Workflow A status
     */
    private function isIndependentWorkflowA(int $statusId): bool
    {
        $status = Status::find($statusId);

        if (!$status) {
            return false;
        }

        // Only "Request Draft CR Doc" is independent (Workflow A)
        return $status->name === 'Request Draft CR Doc';
    }

    /**
     * Check if two statuses are both Workflow A (should not affect each other)
     * Returns true ONLY if:
     * - One is Workflow A ("Request Draft CR Doc")
     * - The other is NOT Workflow A (Workflow B approval)
     */
    private function shouldPreserveForIndependentWorkflow(int $currentStatusId, int $otherStatusId): bool
    {
        $currentIsWorkflowA = $this->isIndependentWorkflowA($currentStatusId);
        $otherIsWorkflowA = $this->isIndependentWorkflowA($otherStatusId);

        // If one is Workflow A and the other is not, they should not affect each other
        // This means: preserve the other status
        if ($currentIsWorkflowA && !$otherIsWorkflowA) {
            return true; // Current is A, other is B - preserve B
        }

        if (!$currentIsWorkflowA && $otherIsWorkflowA) {
            return true; // Current is B, other is A - preserve A
        }

        // Both are Workflow A OR both are Workflow B - they can affect each other normally
        return false;
    }

    /**
     * Get the active status value based on same_time field in new_workflows
     * 
     * @param int $fromStatusId The old/from status ID
     * @param int $toStatusId The new/to status ID
     * @return string '1' for active or '2' for completed
     */
    private function getActiveStatusBySameTime(int $fromStatusId, int $toStatusId): string
    {
        // Check if there's a workflow definition with same_time field
        $workflow = \App\Models\NewWorkFlow::whereHas('workflowstatus', function ($q) use ($fromStatusId, $toStatusId) {
            $q->where('from_status_id', $fromStatusId)
                ->where('to_status_id', $toStatusId);
        })->first();

        if (!$workflow) {
            // If no workflow found, default to active status
            return self::ACTIVE_STATUS;  // '1'
        }

        // Check same_time field
        if (isset($workflow->same_time) && $workflow->same_time == 1) {
            // same_time = 1: Set as completed
            return self::COMPLETED_STATUS;  // '2'
        }

        // same_time = 0 or NULL: Set as active
        return self::ACTIVE_STATUS;  // '1'
    }

    private function validateStatusChange($changeRequest, $statusData, $workflow)
    {
        $currentStatus = $changeRequest->status;
        $newStatus = $statusData['new_status_id'] ?? null;

        // Debug log to see what values we're working with
        \Log::debug('Status change validation', [
            'currentStatus' => $currentStatus,
            'newStatus' => $newStatus,
            'statusData' => $statusData,
        ]);

        // Return false if status hasn't changed (not an error condition)
        if ($currentStatus == $newStatus) {  // Using loose comparison in case of string vs int
            return false;
        }

        // Add other validation rules here if needed
        // Throw exceptions for actual validation failures

        return true;
    }

    /**
     * Extract status data from request
     */
    public function extractStatusData($request): array
    {
        $newStatusId = $request['new_status_id'] ?? $request->new_status_id ?? null;
        $oldStatusId = $request['old_status_id'] ?? $request->old_status_id ?? null;
        $newWorkflowId = $request['new_workflow_id'] ?? null;

        // if (!$newStatusId || !$oldStatusId) {
        //     throw new InvalidArgumentException('Missing required status IDs');
        // }

        return [
            'new_status_id' => $newStatusId,
            'old_status_id' => $oldStatusId,
            'new_workflow_id' => $newWorkflowId,
        ];
    }

    /**
     * Get workflow based on status data
     */
    private function getWorkflow(array $statusData): ?NewWorkFlow
    {
        $workflowId = $statusData['new_workflow_id'] ?: $statusData['new_status_id'];

        return NewWorkFlow::find($workflowId);
    }

    /**
     * Get change request by ID
     */
    private function getChangeRequest(int $id): ChangeRequest
    {
        $changeRequest = ChangeRequest::find($id);

        if (!$changeRequest) {
            throw new Exception("Change request not found: {$id}");
        }

        return $changeRequest;
    }

    /**
     * Determine user ID for the status update
     */
    private function getUserId(ChangeRequest $changeRequest, $request): int
    {
        if (Auth::check()) {
            return Auth::id();
        }
        // Try to get user from division manager email
        if ($changeRequest->division_manager) {
            $user = User::where('email', $changeRequest->division_manager)->first();
            if ($user) {
                return $user->id;
            }
        }

        // Fallback to assigned user
        $assignedTo = $request['assign_to'] ?? null;
        if (!$assignedTo) {
            throw new Exception('Unable to determine user for status update');
        }

        return $assignedTo;
    }

    /**
     * Process the main status update logic
     */
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

        // ═══════════════════════════════════════════════════════════════════════
        // Special Flow: Pending UAT (promo) Deactivation Logic
        // ═══════════════════════════════════════════════════════════════════════
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

        // $this->handleNotifications($statusData, $changeRequest->id, $request);
        // event(new ChangeRequestStatusUpdated($changeRequest, $statusData, $request, $this->active_flag));

    }

    /**
     * Get technical team approval counts
     */
    private function getTechnicalTeamCounts(int $changeRequestId, int $oldStatusId): array
    {
        $technicalCr = TechnicalCr::where('cr_id', $changeRequestId)
            // ->where('status', self::INACTIVE_STATUS)
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
            // ->where('status', self::ACTIVE_STATUS)
            // ->whereIN('status',self::$ACTIVE_STATUS_ARRAY)
            ->whereRaw('CAST(status AS CHAR) = ?', ['1'])
            ->count();

        return ['total' => $total, 'approved' => $approved];
    }

    /**
     * Update the current status record
     */
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
                //->where('active', self::ACTIVE_STATUS)
                //->whereIN('active',self::$ACTIVE_STATUS_ARRAY)
                ->active()
                ->first();

            //to check all the active statuses for this CR
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

        // the current record

        $workflowActive = $workflow->workflow_type == self::WORKFLOW_NORMAL
            ? self::INACTIVE_STATUS
            : self::COMPLETED_STATUS;

        // Log for debugging null created_at issue
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


            // Get the change request
            $changeRequest = ChangeRequest::find($changeRequestId);

            if ($changeRequest) {

                // Update need_ui_ux to 1
                //$changeRequest->update(['need_ui_ux' => 1]);


            } else {
                Log::error('Change request not found for need_ui_ux update', [
                    'cr_id' => $changeRequestId
                ]);
            }
        }
        // Only update if conditions are met
        if ($shouldUpdate) {
            $updateResult = $currentStatus->update([
                'sla_dif' => $slaDifference,
                'active' => self::COMPLETED_STATUS
            ]);

            // to check update result

            $this->handleDependentStatuses($changeRequestId, $currentStatus, $workflowActive);
        } else {
            Log::warning('updateCurrentStatus: Skipped update due to shouldUpdateCurrentStatus=false', [
                'cr_id' => $changeRequestId,
                'status_record_id' => $currentStatus->id
            ]);
        }
    }

    /**
     * Check if current status should be updated
     */
    private function shouldUpdateCurrentStatus(int $oldStatusId, array $technicalTeamCounts): bool
    {
        if ($oldStatusId != self::TECHNICAL_REVIEW_STATUS) {
            return true;
        }

        return $technicalTeamCounts['total'] > 0 &&
            $technicalTeamCounts['total'] == $technicalTeamCounts['approved'];
    }

    /**
     * Calculate SLA difference in days
     */
    private function calculateSlaDifference(?string $createdAt): int
    {
        if (!$createdAt) {
            return 0; // Return 0 if created_at is null
        }

        return Carbon::parse($createdAt)->diffInDays(Carbon::now());
    }

    /**
     * Handle dependent statuses based on workflow type
     */
    /**
     * Handle dependent statuses
     * MODIFIED: Preserves independence between Workflow A and Workflow B
     */
    private function handleDependentStatuses(
        int $changeRequestId,
        ChangeRequestStatus $currentStatus,
        string $workflowActive
    ): void {
        // Get all statuses with the same old_status_id that are still active
        $dependentStatuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('old_status_id', $currentStatus->old_status_id)
            ->active()
            ->get();


        // Check if current status is independent Workflow A
        $currentIsWorkflowA = $this->isIndependentWorkflowA($currentStatus->new_status_id);

        if ($currentIsWorkflowA) {
            // ================================================================
            // WORKFLOW A MODE: Do NOT deactivate Workflow B statuses
            // ================================================================


            $dependentStatuses->each(function ($status) use ($currentStatus, $changeRequestId) {
                // Skip if it's the same record
                if ($status->id === $currentStatus->id) {
                    return;
                }

                // Check if this is a Workflow B status (should be preserved)
                if ($this->shouldPreserveForIndependentWorkflow($currentStatus->new_status_id, $status->new_status_id)) {
                    // DO NOT deactivate - preserve it
                } else {
                    // This would be another Workflow A status (though there's only one)
                    // Deactivate normally
                    $status->update(['active' => self::INACTIVE_STATUS]);
                }
            });

        } else {
            // ================================================================
            // WORKFLOW B OR NORMAL MODE
            // ================================================================


            if (!$workflowActive) {
                // Abnormal workflow - deactivate all dependent statuses
                $dependentStatuses->each(function ($status) use ($currentStatus, $changeRequestId) {
                    // Skip if it's the same record
                    if ($status->id === $currentStatus->id) {
                        return;
                    }

                    // Preserve Workflow A if current is Workflow B
                    if ($this->shouldPreserveForIndependentWorkflow($currentStatus->new_status_id, $status->new_status_id)) {
                        // DO NOT deactivate Workflow A
                    } else {
                        // Normal deactivation for same workflow statuses
                        $status->update(['active' => self::INACTIVE_STATUS]);
                    }
                });
            }
        }
    }

    /**
     * Create new status records based on workflow
     */
    /**
   * Create new status records based on workflow
  /**
   * Create new status records based on workflow
   */



    /**
     * Create new status records for a change request
     * Handles parallel workflows and merge point logic
     * 
     * @param ChangeRequest $changeRequest
     * @param array $statusData
     * @param NewWorkFlow $workflow
     * @param int $userId
     * @param mixed $request
     * @return void
     */
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

        // ═══════════════════════════════════════════════════════════════════════
        // FLEXIBLE PARALLEL WORKFLOWS from "Pending Create Agreed Scope"
        // ═══════════════════════════════════════════════════════════════════════

        $oldStatus = Status::find($statusData['old_status_id']);

        // Get new status from workflow
        $newStatus = null;
        if ($workflow && $workflow->workflowstatus->isNotEmpty()) {
            $newStatus = $workflow->workflowstatus->first()->to_status;
        }

        // Initialize variables
        $shouldCreateParallelWorkflows = false;
        $statusesToCreate = [];

        // ════════════════════════════════════════════════════════════
        // ✨ SPECIAL CASE: "Need Update" from Workflow B statuses
        // Handle transitions from Workflow B back to "Pending Create Agreed Scope"
        // ════════════════════════════════════════════════════════════

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

            // Call our Need Update action
            $this->handleNeedUpdateAction($changeRequest->id);

            // Return early to skip normal workflow processing
            return;
        } else {
            // Enhanced debugging for when conditions are NOT met
        }

        // ════════════════════════════════════════════════════════════
        // Check if we're transitioning from "Pending Create Agreed Scope"
        // ════════════════════════════════════════════════════════════

        if ($oldStatus && $oldStatus->status_name == 'Pending Create Agreed Scope') {


            // ════════════════════════════════════════════════════════════
            // ✨ CASE 1: "Request Draft CR Doc" selected
            // Create ALL 4 statuses (Workflow A + Workflow B)
            // ════════════════════════════════════════════════════════════

            if ($newStatus && $newStatus->status_name == 'Request Draft CR Doc') {

                $shouldCreateParallelWorkflows = true;

                // Create ALL 4 statuses with dynamic group resolution
                $statusesToCreate = [
                    ['status_name' => 'Request Draft CR Doc'],
                    ['status_name' => 'Pending Agreed Scope Approval-SA'],
                    ['status_name' => 'Pending Agreed Scope Approval-Vendor'],
                    ['status_name' => 'Pending Agreed Scope Approval-Business']
                ];

            }

            // ════════════════════════════════════════════════════════════
            // ✨ CASE 2: "Pending Agreed Scope Approval-SA" selected
            // Create ONLY 3 Workflow B statuses (no Workflow A)
            // ════════════════════════════════════════════════════════════
            elseif ($newStatus && $newStatus->status_name === 'Pending Agreed Scope Approval-SA') {

                $shouldCreateParallelWorkflows = true;

                // Create ONLY Workflow B statuses with dynamic group resolution
                $statusesToCreate = [
                    ['status_name' => 'Pending Agreed Scope Approval-SA'],
                    ['status_name' => 'Pending Agreed Scope Approval-Vendor'],
                    ['status_name' => 'Pending Agreed Scope Approval-Business']
                ];

            }

            // ════════════════════════════════════════════════════════════
            // ✨ CASE 3: Any other status selected
            // Use normal workflow (single status)
            // ════════════════════════════════════════════════════════════
            else {
            }
        }

        // ═══════════════════════════════════════════════════════════════════════
        // CREATE PARALLEL WORKFLOW STATUSES
        // ═══════════════════════════════════════════════════════════════════════

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

                // Determine workflow type
                $workflowType = ($statusName === 'Request Draft CR Doc') ? 'Workflow A' : 'Workflow B';

                $activeStatus = self::ACTIVE_STATUS;

                // Use dynamic group resolution like normal workflow
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


            return;  // Exit early - parallel workflow complete
        }

        // ═══════════════════════════════════════════════════════════════════════
        // ⭐ SPECIAL CASE: "Fix Defect-3rd Parties" / "Fix Defect-Vendor" → "IOT In progress"
        // Force the new row to active=1 and exit early.
        // Without this, determineActiveStatus may return active=0 because
        // sibling rows sharing the same old_status_id are still counted as
        // active dependencies, even though the source row is already set to
        // active=2 by updateCurrentStatus.
        // ═══════════════════════════════════════════════════════════════════════

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
                self::ACTIVE_STATUS   // ← always 1 for this transition
            );

            $this->statusRepository->create($payload);
            $this->active_flag = self::ACTIVE_STATUS;


            return; // Exit early — normal loop must not run
        }

        // ═══════════════════════════════════════════════════════════════════════
        // NORMAL WORKFLOW PROCESSING
        // For all other cases (creates single status)
        // ═══════════════════════════════════════════════════════════════════════

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
            // For SR Workflow (type 15), check view_sr_technical_team_flag instead
            if ($changeRequest->workflow_type_id == 15) {
                $viewTechFlag = $newStatusRow?->view_sr_technical_team_flag ?? false;
            }

            if ($viewTechFlag) {
                $previous_technical_teams = [];
                // Use appropriate technical record based on workflow type
                $techRecord = $changeRequest->workflow_type_id == 15 ? $changeRequest->srTechnicalCrFirst : $changeRequest->technical_Cr_first;

                if ($changeRequest && $techRecord) {
                    $previous_technical_teams = $techRecord->technical_cr_team
                        ? $techRecord->technical_cr_team->pluck('group_id')->toArray()
                        : [];
                }

                // For SR Workflow (type 15), also check sr_technical_teams from the request
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
    /**
     * Check if workflow status should be skipped
     */
    private function shouldSkipWorkflowStatus(
        ChangeRequest $changeRequest,
        $workflowStatus,
        array $statusData
    ): bool {
        // Skip design status if design duration is 0
        return $changeRequest->design_duration == '0'
            && $workflowStatus->to_status_id == 40
            && $statusData['old_status_id'] == 74;
    }

    /**
     * Determine if new status should be active
     */

    /**
     * Determine if new status should be active
     *
     * Priority order:
     *  1. Workflow A origin ("Request Draft CR Doc") → always active
     *  2. Merge point: "Pending SA HL Feedback"
     *     – All 3 parallel approval statuses (SA / Vendor / Business) must
     *       arrive here before the last one is activated.
     *  3. Merge point: "Pending Update Agreed Requirements"
     *     – Both Workflow A + B must arrive before the last one is activated.
     *  4. Normal / fallback logic (workflow_type_id = 9 or generic).
     */
    private function determineActiveStatus(
        int $changeRequestId,
        $workflowStatus,
        NewWorkFlow $workflow,
        int $oldStatusId,
        int $newStatusId,
        ChangeRequest $changeRequest
    ): string {

        // ════════════════════════════════════════════════════════════════════
        // Priority 1: Workflow A origin — always active immediately
        // ════════════════════════════════════════════════════════════════════
        $fromStatus = Status::find($oldStatusId);
        if ($fromStatus && $fromStatus->status_name === 'Request Draft CR Doc') {
            return self::ACTIVE_STATUS;
        }

        // ════════════════════════════════════════════════════════════════════
        // Priority 2: MERGE POINT — "Pending SA HL Feedback"
        //
        // The three parallel approval statuses:
        //   • Pending Agreed Scope Approval-SA
        //   • Pending Agreed Scope Approval-Vendor
        //   • Pending Agreed Scope Approval-Business
        // all converge here. Only the LAST arrival should be active = 1.
        // ════════════════════════════════════════════════════════════════════
        $pendingSaHlStatus = Status::where('status_name', 'Pending SA HL Feedback')->first();

        if ($pendingSaHlStatus && (int) $workflowStatus->to_status_id === (int) $pendingSaHlStatus->id) {

            // Collect the three parallel approval status IDs (already initialised in __construct)
            $agreedScopeStatusIds = array_values(array_filter([
                self::$PENDING_AGREED_SCOPE_SA_STATUS_ID,
                self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID,
                self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID,
            ]));

            // Only apply merge logic when the transition is coming from one of these three
            if (!empty($agreedScopeStatusIds) && in_array($oldStatusId, $agreedScopeStatusIds, true)) {

                $requiredCount = count($agreedScopeStatusIds); // 3

                // Count how many of the 3 have ALREADY inserted a row pointing to Pending SA HL Feedback
                // (the current row is not yet in the DB, so we add 1)
                $alreadyInserted = ChangeRequestStatus::where('cr_id', $changeRequestId)
                    ->where('new_status_id', $pendingSaHlStatus->id)
                    ->whereIn('old_status_id', $agreedScopeStatusIds)
                    ->count();

                $totalAfterInsert = $alreadyInserted + 1;


                if ($totalAfterInsert >= $requiredCount) {
                    // All 3 parallel approvals have arrived → activate this last row
                    $this->active_flag = self::ACTIVE_STATUS;
                    return self::ACTIVE_STATUS;
                }

                // Not all 3 have approved yet → keep this row inactive
                $this->active_flag = self::INACTIVE_STATUS;
                return self::INACTIVE_STATUS;
            }
        }

        // ════════════════════════════════════════════════════════════════════
        // Priority 3: MERGE POINT — "Pending Update Agreed Requirements"
        //
        // Both Workflow A (Request Draft CR Doc) and Workflow B
        // (any of the 3 approval statuses) must arrive before activating.
        // ════════════════════════════════════════════════════════════════════
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

            // CR did NOT use parallel workflows → fall through to normal logic
        }

        // ════════════════════════════════════════════════════════════════════
        // Priority 4: Normal / fallback logic
        // ════════════════════════════════════════════════════════════════════
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

    /**
     * Check if this change request used the parallel workflow feature
     * 
     * @param int $crId
     * @return bool
     */
    private function didUseParallelWorkflows(int $crId): bool
    {
        $sourceStatus = Status::where('status_name', 'Pending Create Agreed Scope')->first();

        if (!$sourceStatus) {
            return false;
        }

        // Workflow A status
        $workflowAStatusId = Status::where('status_name', 'Request Draft CR Doc')->value('id');

        // Workflow B statuses
        $workflowBStatusIds = Status::whereIn('status_name', [
            'Pending Agreed Scope Approval-SA',
            'Pending Agreed Scope Approval-Vendor',
            'Pending Agreed Scope Approval-Business'
        ])->pluck('id')->toArray();

        if (!$workflowAStatusId || empty($workflowBStatusIds)) {
            return false;
        }

        // ✨ KEY: Check if BOTH workflows exist
        $hasWorkflowA = ChangeRequestStatus::where('cr_id', $crId)
            ->where('old_status_id', $sourceStatus->id)
            ->where('new_status_id', $workflowAStatusId)
            ->exists();

        $hasWorkflowB = ChangeRequestStatus::where('cr_id', $crId)
            ->where('old_status_id', $sourceStatus->id)
            ->whereIn('new_status_id', $workflowBStatusIds)
            ->exists();

        // Both must exist!
        $hasBothWorkflows = $hasWorkflowA && $hasWorkflowB;


        return $hasBothWorkflows;
    }
    /**
     * Check workflow dependencies
     */
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

    /**
     * Check if a specific dependency is met
     */
    private function isDependencyMet(int $changeRequestId, int $workflowId): bool
    {
        $dependentWorkflow = NewWorkFlow::find($workflowId);

        if (!$dependentWorkflow) {
            return false;
        }

        return ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $dependentWorkflow->from_status_id)
            ->where('old_status_id', $dependentWorkflow->previous_status_id)
            // ->where('active', self::COMPLETED_STATUS)
            // ->whereIN('active',self::$COMPLETED_STATUS_ARRAY)
            ->completed()
            ->exists();
    }

    /**
     * Check dependent workflows for normal workflow type
     */
    private function checkDependentWorkflows(int $changeRequestId, NewWorkFlow $workflow): string
    {
        $dependentStatuses = ChangeRequestStatus::where('cr_id', $changeRequestId)
            // ->where('active', self::ACTIVE_STATUS)
            // ->whereIN('active',self::$ACTIVE_STATUS_ARRAY)
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
            // ->where('active', self::ACTIVE_STATUS)
            // ->whereIN('active',self::$ACTIVE_STATUS_ARRAY)
            ->active()
            ->count();

        return $dependentCount > 0 ? self::INACTIVE_STATUS : self::ACTIVE_STATUS;
    }

    /**
     * Build status data array
     */
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
            'active' => $active, // '0' | '1' | '2'
        ];
    }

    /**
     * Handle email notifications
     */
    private function handleNotifications(array $statusData, int $changeRequestId, $request): void
    {
        // dd($request->all());
        // Notify CR Manager when status changes from 99 to 101
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
        /*
        // Notify Dev Team When status changes to Technical Estimation or Pending Implementation or Technical Implementation
        $assigned_user_id = null;
        if(isset($request->assignment_user_id)){
             $assigned_user_id = $request->assignment_user_id;
        }
        $devTeamStatuses = [config('change_request.status_ids.technical_estimation'),config('change_request.status_ids.pending_implementation'),config('change_request.status_ids.technical_implementation')];
        $newStatusId = NewWorkFlowStatuses::where('new_workflow_id', $statusData['new_status_id'])->get()->pluck('to_status_id')->toArray();
        //dd($newStatusId);
        if (array_intersect($devTeamStatuses, $newStatusId) && $this->active_flag == '1') {
            try {
                 $this->mailController->notifyDevTeam($changeRequestId , $statusData['old_status_id'] , $newStatusId, $assigned_user_id);
             } catch (\Exception $e) {
                 Log::error('Failed to send Dev Team notification', [
                     'change_request_id' => $changeRequestId,
                     'error' => $e->getMessage()
                 ]);
             }
        }
        */

        // Notify group when status changes.
        // dd($request->all(), $statusData);
        $newStatusId = NewWorkFlowStatuses::where(
            'new_workflow_id',
            $request->new_status_id
        )->get()->pluck('to_status_id')->toArray();
        // dd($newStatusId);
        $userToNotify = [];
        if (in_array(\App\Services\StatusConfigService::getStatusId('pending_cd_analysis'), $newStatusId)) {
            if (!empty($request->cr_member)) {
                $userToNotify = [$request->cr_member];
            }
        }

        $cr = ChangeRequest::find($changeRequestId);
        $targetStatus = Status::with('group_statuses')->whereIn('id', $newStatusId)->first();
        // $group_id = $targetStatus->group_statuses->first()->group_id ?? null;
        $viewGroup = GroupStatuses::where('status_id', $targetStatus->id)->where(
            'type',
            '2'
        )->pluck('group_id')->toArray();
        $group_id = $cr->application->group_applications->first()->group_id ?? null;
        // will check if group_id is in viewGroup then we will send the notification to this group is only
        // dd($group_id,$viewGroup);
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
        // dd($groupToNotify);

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

    /**
     * Check if status transition exists
     */
    private function hasStatusTransition(int $changeRequestId, int $toStatusId): bool
    {
        return ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $toStatusId)
            ->exists();
    }

    // Get the dependency service (lazy loaded)
    private function getDependencyService(): CrDependencyService
    {
        if (!$this->dependencyService) {
            $this->dependencyService = new CrDependencyService();
        }

        return $this->dependencyService;
    }

    /**
     * Get workflow ID for "Need Update" transition dynamically
     * This replaces the hardcoded workflow ID 8370
     */
    private function getNeedUpdateWorkflowId(int $changeRequestId, array $statusData): ?int
    {
        try {
            // Get the change request to determine workflow type
            $changeRequest = ChangeRequest::find($changeRequestId);
            if (!$changeRequest) {
                Log::error('Change request not found for workflow lookup', [
                    'cr_id' => $changeRequestId
                ]);
                return null;
            }


            // Get the status IDs for the "Need Update" transition
            // From: Any parallel workflow status
            // To: "Pending Create Agreed Scope"
            $toStatusId = $this->getStatusIdByName('Pending Create Agreed Scope');
            if (!$toStatusId) {
                Log::error('Target status "Pending Create Agreed Scope" not found', [
                    'cr_id' => $changeRequestId
                ]);
                return null;
            }


            // The "Need Update" can come from any of the parallel workflow statuses
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

            // Filter out null values
            $fromStatusIds = array_filter($fromStatusIds);
            if (empty($fromStatusIds)) {
                Log::error('No valid source statuses found for Need Update transition', [
                    'cr_id' => $changeRequestId
                ]);
                return null;
            }


            // Try to find workflow ID for any of the possible transitions
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

    /**
     * Get workflow ID by status transition
     */
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

    /**
     * Get status ID by status name
     */
    private function getStatusIdByName(string $statusName): ?int
    {
        $status = Status::where('status_name', $statusName)
            ->where('active', '1')
            ->first();

        return $status ? $status->id : null;
    }

    /**
     * Handle agreed scope approval transition logic
     * When any approval status transitions to "Pending Create Agreed Scope",
     * deactivate other approval statuses and create new active record
     */
    /**
     * Handle agreed scope approval transition logic
     * When vendor workflow is in any "Pending Agreed Scope Approval" status
     * and transitions to "Pending Create Agreed Scope" (Need Update - Status ID 8370),
     * this method:
     * 1. Archives ALL related records (active: any → 2) including the 4 parallel workflows
     * 2. Takes the last record from change_request_statuses where active=2
     * 3. Reinserts it with active=1 to make it the current active status
     * 
     * @param int $crId The Change Request ID
     * @param array $statusData Status transition data
     * @return void
     */

    private function isNeedUpdateTransition(int $crId, array $statusData): bool
    {
        $newStatusId = $statusData['new_status_id'] ?? null;
        $needUpdateWorkflowId = $this->getNeedUpdateWorkflowId($crId, $statusData);

        // Only handle if transitioning TO the dynamic Need Update workflow ID
        return $newStatusId === $needUpdateWorkflowId;
    }
    private function handleAgreedScopeApprovalTransition(int $crId, array $statusData): void
    {

        // Define the 4 parallel workflow status IDs that need to be archived
        // Based on your data: 292, 293, 294, 295
        $parallelWorkflowStatusIds = [
            self::$PENDING_AGREED_SCOPE_SA_STATUS_ID,        // 292
            self::$PENDING_AGREED_SCOPE_VENDOR_STATUS_ID,    // 293
            self::$PENDING_AGREED_SCOPE_BUSINESS_STATUS_ID,  // 294
            self::$REQUEST_DRAFT_CR_DOC_STATUS_ID,           // 295
        ];

        // Filter out any null values
        $parallelWorkflowStatusIds = array_filter($parallelWorkflowStatusIds);

        if (empty($parallelWorkflowStatusIds)) {
            Log::error('No parallel workflow status IDs configured', [
                'cr_id' => $crId
            ]);
            throw new Exception('Parallel workflow status IDs not configured');
        }


        // Step 1: Archive ALL records for these 4 statuses (active: 0 or 1 → 2)
        $archivedCount = ChangeRequestStatus::where('cr_id', $crId)
            ->whereIn('new_status_id', $parallelWorkflowStatusIds)
            ->whereIn('active', ['0', '1'])
            ->update(['active' => self::COMPLETED_STATUS]); // active = '2'


        // Step 2: Get the LAST archived record (highest ID where active=2)
        $lastArchivedRecord = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::COMPLETED_STATUS) // active = '2'
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastArchivedRecord) {
            Log::error('No archived record found to reinsert', [
                'cr_id' => $crId
            ]);
            throw new Exception("No archived record found for CR {$crId}");
        }


        // Step 3: Archive ANY other active records (safety measure)
        $otherActiveCount = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', self::ACTIVE_STATUS) // active = '1'
            ->update(['active' => self::COMPLETED_STATUS]); // active = '2'

        if ($otherActiveCount > 0) {
        }

        // Step 4: Reinsert the last archived record with active=1
        $newActiveRecord = $lastArchivedRecord->replicate();
        $newActiveRecord->active = self::ACTIVE_STATUS; // active = '1'
        $newActiveRecord->created_at = now();
        $newActiveRecord->updated_at = null;
        $newActiveRecord->save();


    }


    /**
     * Handle "Need Update" action for Change Request
     * This implements the business logic for when user selects "Need Update" from UI
     * 
     * @param int $crId The Change Request ID
     * @return bool Success status
     * @throws Exception
     */
    public function handleNeedUpdateAction(int $crId): bool
    {

        try {
            DB::beginTransaction();

            // Step 1: Identify the current Change Request
            $changeRequest = ChangeRequest::find($crId);
            if (!$changeRequest) {
                throw new Exception("Change Request not found: {$crId}");
            }

            // Step 2: Define the parallel approval status names
            $parallelStatusNames = [
                'Pending Agreed Scope Approval-SA',
                'Pending Agreed Scope Approval-Vendor',
                'Pending Agreed Scope Approval-Business',
                'Request Draft CR Doc'
            ];

            // Step 3: Get the status IDs dynamically
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

            // Step 4: Update all active records in change_request_statuses 
            // where new_status_id represents any of the parallel statuses
            $deactivatedCount = ChangeRequestStatus::where('cr_id', $crId)
                ->whereIn('new_status_id', $parallelStatusIds)
                ->active()
                ->update(['active' => self::INACTIVE_STATUS]);


            // If no parallel statuses were found to deactivate, don't proceed with duplication
            // This prevents creating unnecessary duplicate records
            if ($deactivatedCount === 0) {
                DB::commit();
                return false; // Return false to indicate no action was needed
            }

            // Step 5: Retrieve the latest status record for the same CR
            $latestStatusRecord = ChangeRequestStatus::where('cr_id', $crId)
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$latestStatusRecord) {
                throw new Exception("No status records found for CR: {$crId}");
            }


            // Step 6: Duplicate this latest record without changing any data, including new_status_id
            // Use direct DB insertion to completely avoid triggering model events and workflow logic
            $duplicatedRecord = $latestStatusRecord->replicate();
            $duplicatedRecord->active = self::ACTIVE_STATUS; // Only update active = 1
            $duplicatedRecord->created_at = now(); // Set new creation timestamp
            $duplicatedRecord->updated_at = null; // Keep consistent with model behavior

            // Ensure created_at is not null
            if (!$duplicatedRecord->created_at) {
                $duplicatedRecord->created_at = now();
            }

            // Use direct DB insert to avoid any model events or automatic workflow creation
            $insertData = $duplicatedRecord->toArray();
            unset($insertData['id']); // Remove ID to let database generate new one

            $newRecordId = DB::table('change_request_statuses')->insertGetId($insertData);


            DB::commit();

            // Step 7: Clean up any parallel statuses that might have been created by workflow triggers
            // This ensures that even if something creates parallel statuses after our action,
            // we clean them up to maintain the correct state
            $cleanupCount = ChangeRequestStatus::where('cr_id', $crId)
                ->whereIn('new_status_id', $parallelStatusIds)
                ->where('active', '1')
                ->where('id', '>', $newRecordId) // Only clean up records created after our action
                ->update(['active' => self::INACTIVE_STATUS]);

            if ($cleanupCount > 0) {
            }

            // Step 8: Also clean up any parallel statuses that were created in the same transaction
            // This handles the case where workflow creates parallel statuses before our action
            $sameTransactionCleanup = ChangeRequestStatus::where('cr_id', $crId)
                ->whereIn('new_status_id', $parallelStatusIds)
                ->where('active', '1')
                ->where('created_at', '>=', now()->subMinutes(5)) // Records created in the last 5 minutes
                ->where('id', '!=', $newRecordId) // Don't deactivate our own record
                ->update(['active' => self::COMPLETED_STATUS]); // Use COMPLETED_STATUS (2) instead of INACTIVE_STATUS (0)

            if ($sameTransactionCleanup > 0) {
            }


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

    // Check if this is a transition from Pending CAB status to pending design status workflow 160
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
            ->where('workflow_type', '0') // Normal workflow (not reject)
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

        /*return isset($statusData['old_status_id']) &&
               (int)$statusData['old_status_id'] === self::$PENDING_CAB_STATUS_ID;*/
        return isset($statusData['new_status_id']) &&
            (int) $statusData['new_status_id'] === $workflow->id;
    }

    // Check if CR has reached Delivered status and fire event
    private function checkAndFireDeliveredEvent(ChangeRequest $changeRequest, array $statusData): void
    {
        $newWorkflowId = $statusData['new_status_id'] ?? null;
        if (!$newWorkflowId) {
            return; // no workflow do nothing
        }

        $workflow = NewWorkFlow::with('workflowstatus')->find($newWorkflowId);
        if (!$workflow) {
            return; // no workflow do nothing
        }

        foreach ($workflow->workflowstatus as $wfStatus) {
            if (in_array((int) $wfStatus->to_status_id, [self::$DELIVERED_STATUS_ID, self::$REJECTED_STATUS_ID], true)) {
                // Refresh the CR to ensure we have the latest data
                $changeRequest->refresh();

                // the status delivered fire the event
                event(new CrDeliveredEvent($changeRequest));

                return;
            }
        }
    }
}