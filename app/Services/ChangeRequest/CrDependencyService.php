<?php


namespace App\Services\ChangeRequest;
use App\Models\Change_request;
use App\Models\CrDependency;
use App\Models\NewWorkFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;

/**
 * CrDependencyService
 * 
 * Handles all business logic related to CR dependencies:
 * - Syncing dependencies from the "depend_on" custom field
 * - Checking if a CR should be held due to dependencies
 * - Releasing CRs when their dependencies are delivered or rejected.
 */
class CrDependencyService
{
    /**
     * Status IDs - using hardcoded values as confirmed by user
     */
    private static int $PENDING_CAB_STATUS_ID;
    private static int $DELIVERED_STATUS_ID;

    /**
     * The target status that triggers dependency check
     * This is the first status after Pending CAB (Design Estimation)
     */
    private ?int $designEstimationStatusId = null;

    protected ChangeRequestStatusService $statusService;

    public function __construct()
    {
        self::$PENDING_CAB_STATUS_ID = \App\Services\StatusConfigService::getStatusId('pending_cab');
        self::$DELIVERED_STATUS_ID = \App\Services\StatusConfigService::getStatusId('Delivered');
        $this->statusService = new ChangeRequestStatusService();
    }

    /**
     * Sync dependencies from the "depend_on" custom field value
     * 
     * @param int $crId The CR that has dependencies
     * @param array $dependsOnCrNos Array of CR numbers (from multiselect field)
     */
    public function syncDependencies(int $crId, array $dependsOnCrNos): void
    {
        if (empty($dependsOnCrNos)) {
            Log::info('No dependencies provided', [
                'cr_id' => $crId,
            ]);
            // If empty, mark all existing dependencies as resolved
            CrDependency::where('cr_id', $crId)
                ->active()
                ->update(['status' => CrDependency::STATUS_RESOLVED]);
            
            // Clear the dependency hold if it was set
            $this->clearDependencyHold($crId);
            return;
        }
        Log::info('Dependencies provided', [
            'cr_id' => $crId,
            'depends_on_cr_nos' => $dependsOnCrNos,
        ]);

        // Get the current CR's cr_no to prevent self-dependency
        $currentCr = Change_request::find($crId);
        if ($currentCr) {
            Log::info('Current CR', [
                'cr_id' => $crId,
                'current_cr' => $currentCr,
            ]);
            // Filter out self-reference (defense in depth - UI should also prevent this)
            $dependsOnCrNos = array_filter($dependsOnCrNos, function($crNo) use ($currentCr) {
                return (int)$crNo !== (int)$currentCr->cr_no;
            });
        }

        if (empty($dependsOnCrNos)) {
            Log::info("CrDependencyService: No valid dependencies after filtering", [
                'cr_id' => $crId,
            ]);
            // Clear any existing dependencies
            CrDependency::where('cr_id', $crId)
                ->active()
                ->update(['status' => CrDependency::STATUS_RESOLVED]);
            $this->clearDependencyHold($crId);
            return;
        }
        Log::info("CrDependencyService: Valid dependencies after filtering", [
            'cr_id' => $crId,
            'depends_on_cr_nos' => $dependsOnCrNos,
        ]);

        // Convert CR numbers to CR IDs
        $dependsOnCrIds = Change_request::whereIn('cr_no', $dependsOnCrNos)
            ->pluck('id')
            ->toArray();

        // Validate circular dependency (Prevent saving cr1->cr2 if cr2->cr1 exists couldn't depend on each other)
        $dependsOnCrIds = array_filter($dependsOnCrIds, function($dependsOnId) use ($crId) {
             // Check if dependsOnId already depends on crId
             $isCircular = CrDependency::where('cr_id', $dependsOnId)
                ->where('depends_on_cr_id', $crId)
                ->active()
                ->exists();
             
             if ($isCircular) {
                 Log::warning("CrDependencyService: prevented circular dependency", [
                     'cr_id' => $crId,
                     'would_depend_on' => $dependsOnId
                 ]);
                 return false;
             }
             return true;
        });

        if (empty($dependsOnCrIds)) {
            Log::warning("CrDependencyService: No valid CRs found for CR numbers or all filtered due to circular dependency", [
                'cr_id' => $crId,
                'cr_nos' => $dependsOnCrNos
            ]);
            return;
        }

        DB::transaction(function () use ($crId, $dependsOnCrIds) {
            // Get current active dependencies
            $currentDeps = CrDependency::where('cr_id', $crId)
                ->active()
                ->pluck('depends_on_cr_id')
                ->toArray();

            // Find dependencies to add
            $toAdd = array_diff($dependsOnCrIds, $currentDeps);
            
            // Find dependencies to remove (mark as resolved)
            $toRemove = array_diff($currentDeps, $dependsOnCrIds);

            // Add new dependencies
            foreach ($toAdd as $dependsOnCrId) {
                CrDependency::updateOrCreate(
                    [
                        'cr_id' => $crId,
                        'depends_on_cr_id' => $dependsOnCrId,
                    ],
                    [
                        'status' => CrDependency::STATUS_ACTIVE,
                    ]
                );
            }

            // Mark removed dependencies as resolved
            if (!empty($toRemove)) {
                CrDependency::where('cr_id', $crId)
                    ->whereIn('depends_on_cr_id', $toRemove)
                    ->update(['status' => CrDependency::STATUS_RESOLVED]);
            }

            Log::info("CrDependencyService: Synced dependencies", [
                'cr_id' => $crId,
                'added' => $toAdd,
                'removed' => $toRemove,
            ]);
        });
    }

    /**
     * Check if a CR should be held due to unresolved dependencies
     * 
     * @param int $crId The CR attempting to transition
     * @return bool True if CR should be held
     */
    public function shouldHoldCr(int $crId): bool
    {
        $activeDepCount = CrDependency::where('cr_id', $crId)
            ->active()
            ->count();
        Log::info('Active dependency count', [
            'cr_id' => $crId,
            'active_dep_count' => $activeDepCount,
        ]);

        if ($activeDepCount === 0) {
            return false;
        }

        // Check if all dependencies are delivered
        $activeDeps = CrDependency::where('cr_id', $crId)
            ->active()
            ->with('dependsOnCr.currentRequestStatuses')
            ->get();
        Log::info('Active dependencies', [
            'cr_id' => $crId,
            'active_deps' => $activeDeps,
        ]);

        foreach ($activeDeps as $dep) {
            $blockerCr = $dep->dependsOnCr;
            Log::info('Blocker CR', [
                'cr_id' => $crId,
                'blocker_cr' => $blockerCr,
            ]);
            if (!$blockerCr) {
                continue;
            }

            $currentStatus = $blockerCr->currentRequestStatuses;
            Log::info('Current status', [
                'cr_id' => $crId,
                'current_status' => $currentStatus,
            ]);
            if (!$currentStatus || $currentStatus->new_status_id != self::$DELIVERED_STATUS_ID) {
                // This dependency is not delivered yet
                Log::info('Dependency is not delivered yet', [
                    'cr_id' => $crId,
                    'active_deps' => $activeDeps,
                ]);
                return true;
            }
        }
        Log::info('All dependencies are delivered', [
            'cr_id' => $crId,
            'active_deps' => $activeDeps,
        ]);

        // All dependencies are delivered, no need to hold
        return false;
    }

    /**
     * Apply dependency hold to a CR
     * Only sets the boolean flag - blocking CRs are queried from cr_dependencies table
     * 
     * @param int $crId The CR to hold
     */
    public function applyDependencyHold(int $crId): void
    {
        $cr = Change_request::find($crId);
        if (!$cr) {
            return;
        }

        $cr->update([
            'is_dependency_hold' => true,
        ]);

        Log::info("CrDependencyService: Applied dependency hold", [
            'cr_id' => $crId,
            'cr_no' => $cr->cr_no,
            'blocking_crs' => $this->getBlockingCrNumbers($crId),
        ]);
    }

    /**
     * Clear dependency hold from a CR
     * Only clears the boolean flag - dependencies remain in cr_dependencies table for history
     * 
     * @param int $crId The CR to release
     */
    public function clearDependencyHold(int $crId): void
    {
        Change_request::where('id', $crId)->update([
            'is_dependency_hold' => false,
        ]);
    }

    /**
     * Get CR numbers that are blocking a specific CR
     * 
     * @param int $crId The blocked CR
     * @return array Array of CR numbers
     */
    public function getBlockingCrNumbers(int $crId): array
    {
        return CrDependency::where('cr_id', $crId)
            ->active()
            ->with('dependsOnCr:id,cr_no')
            ->get()
            ->pluck('dependsOnCr.cr_no')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Handle when a CR is delivered - check and release dependent CRs
     * 
     * @param Change_request $deliveredCr The CR that was just delivered
     */
    public function handleCrDelivered(Change_request $deliveredCr): void
    {
        Log::info("CrDependencyService: Handling CR delivery", [
            'cr_id' => $deliveredCr->id,
            'cr_no' => $deliveredCr->cr_no,
        ]);

        // Find all CRs that depend on this delivered CR
        $dependentCrIds = CrDependency::getDependentCrIds($deliveredCr->id);

        if (empty($dependentCrIds)) {
            Log::info("CrDependencyService: No dependent CRs found");
            return;
        }

        // Mark dependencies on this CR as resolved
        CrDependency::where('depends_on_cr_id', $deliveredCr->id)
            ->active()
            ->update(['status' => CrDependency::STATUS_RESOLVED]);

        // Check each dependent CR
        foreach ($dependentCrIds as $dependentCrId) {
            $this->checkAndReleaseCr($dependentCrId);
        }
    }

    /**
     * Check if a CR can be released and trigger status progression
     * 
     * @param int $crId The CR to check
     */
    protected function checkAndReleaseCr(int $crId): void
    {
        $cr = Change_request::find($crId);
        if (!$cr) {
            return;
        }

        // Only process CRs that are on dependency hold
        if (!$cr->is_dependency_hold) {
            Log::info("CrDependencyService: CR not on dependency hold, skipping", [
                'cr_id' => $crId,
            ]);
            return;
        }

        // Check if ALL dependencies are now resolved
        $hasActiveDepends = CrDependency::where('cr_id', $crId)
            ->active()
            ->exists();

        if ($hasActiveDepends) {
            // Still has unresolved dependencies - log for debugging
            // No need to update any column since blocking CRs are queried from cr_dependencies table
            Log::info("CrDependencyService: CR still has unresolved dependencies", [
                'cr_id' => $crId,
                'blocking_crs' => $this->getBlockingCrNumbers($crId),
            ]);
            return;
        }

        // All dependencies resolved - release the CR and progress status
        $this->releaseCrAndProgressStatus($cr);
    }

    /**
     * Release a CR from dependency hold and progress its status
     * 
     * @param Change_request $cr The CR to release
     */
    protected function releaseCrAndProgressStatus(Change_request $cr): void
    {
        Log::info("CrDependencyService: Releasing CR from dependency hold", [
            'cr_id' => $cr->id,
            'cr_no' => $cr->cr_no,
        ]);

        // Clear the dependency hold
        $this->clearDependencyHold($cr->id);

        // Get the workflow for transitioning from Pending CAB
        $workflow = NewWorkFlow::where('from_status_id', self::$PENDING_CAB_STATUS_ID)
            ->where('type_id', $cr->workflow_type_id)
            ->where('workflow_type', '0') // Normal workflow (not reject)
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->first();

        if (!$workflow) {
            Log::warning("CrDependencyService: No workflow found for status transition", [
                'cr_id' => $cr->id,
                'from_status' => self::$PENDING_CAB_STATUS_ID,
                'workflow_type' => $cr->workflow_type_id,
            ]);
            return;
        }

        // Create a synthetic request object for the status update
        $request = new Request([
            'old_status_id' => self::$PENDING_CAB_STATUS_ID,
            //'new_status_id' => 160, //temporary (this is the workflow id of the next status (design estimation))
            'new_status_id' => $workflow->id,
            'released_from_hold' => true,
        ]);

        // DEBUG: Log the request being sent
        Log::debug('CrDependencyService: About to call UpateChangeRequestStatus', [
            'cr_id' => $cr->id,
            'cr_no' => $cr->cr_no,
            'old_status_id' => self::$PENDING_CAB_STATUS_ID,
            'new_status_id_workflow' => $workflow->id,
            'workflow_from_status' => $workflow->from_status_id,
            'workflow_type' => $workflow->workflow_type,
        ]);

        try {
            // CRITICAL: to clear reference_status from the request before calling update
            request()->merge(['reference_status' => null]);
            
            $repo = new ChangeRequestRepository();
            $repo->UpateChangeRequestStatus($cr->id, $request);
            //$this->statusService->updateChangeRequestStatus($cr->id, $request);
            
            Log::info("CrDependencyService: Successfully progressed CR status", [
                'cr_id' => $cr->id,
                'cr_no' => $cr->cr_no,
                'new_workflow_id' => $workflow->id,
            ]);
        } catch (\Exception $e) {
            Log::error("CrDependencyService: Failed to progress CR status", [
                'cr_id' => $cr->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a status transition is from Pending CAB to the next status
     * 
     * @param array $statusData Status data from request
     * @return bool
     */
    public function isTransitionFromPendingCab(array $statusData): bool
    {
        return isset($statusData['old_status_id']) && 
               (int)$statusData['old_status_id'] === self::$PENDING_CAB_STATUS_ID;
    }

    /**
     * Check if a CR has just reached Delivered status
     * 
     * @param array $statusData Status data from request
     * @param int $crId The CR ID
     * @return bool
     */
    public function hasReachedDelivered(array $statusData, int $crId): bool
    {
        // Get the actual status IDs from the workflow
        $newWorkflowId = $statusData['new_status_id'] ?? null;
        if (!$newWorkflowId) {
            return false;
        }

        $workflow = NewWorkFlow::with('workflowstatus')->find($newWorkflowId);
        if (!$workflow) {
            return false;
        }

        foreach ($workflow->workflowstatus as $wfStatus) {
            if ((int)$wfStatus->to_status_id === self::$DELIVERED_STATUS_ID) {
                return true;
            }
        }

        return false;
    }

    
    public function getDeliveredStatusId(): int
    {
        return self::$DELIVERED_STATUS_ID;
    }

    public function getPendingCabStatusId(): int
    {
        return self::$PENDING_CAB_STATUS_ID;
    }
}
