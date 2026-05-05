<?php

namespace App\Services\ChangeRequest\SpecialFlows;

use App\Models\Change_request_statuse as ChangeRequestStatus;
use App\Models\Status;
use App\Models\NewWorkFlow;
use Illuminate\Support\Facades\Log;

/**
 * IotTcsFlowService
 *
 * Handles the IOT TCs parallel workflow logic:
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │  Pending IOT TCs Review QC  (active=1)│──► IOT TCs Review QC  (independent)    │
 * │  Pending IOT TCs Review SA  (active=1)│──► IOT TCs Review vendor (active=1)    │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * Rules:
 *  - Both "Pending IOT TCs Review QC" and "Pending IOT TCs Review SA" are active at the same time.
 *  - Transitioning QC branch (→ IOT TCs Review QC) is fully independent:
 *      it does NOT deactivate the SA branch record and does NOT trigger any merge logic.
 *  - Transitioning SA branch (→ IOT TCs Review vendor) creates the vendor review status:
 *      • Completes the SA pending record and creates IOT TCs Review vendor with active=1
 *      • Does NOT auto-transition to IOT In progress - manual workflow handles that step
 *      • The QC branch remains independent and can complete on its own timeline
 */
class IotTcsFlowService
{
    // ── Status name constants ────────────────────────────────────────────────
    private const STATUS_PENDING_QC  = 'Pending IOT TCs Review QC';
    private const STATUS_PENDING_SA  = 'Pending IOT TCs Review  SA';
    private const STATUS_REVIEW_QC   = 'IOT TCs Review QC';
    private const STATUS_REVIEW_SA   = 'IOT TCs Review vendor';
    private const STATUS_IOT_IN_PROG = 'IOT In Progress';
    private const STATUS_UPDATE_IOT  = 'Update IOT TCs';

    // ── Cached status IDs ────────────────────────────────────────────────────
    private ?int $pendingQcId  = null;
    private ?int $pendingSaId  = null;
    private ?int $reviewQcId   = null;
    private ?int $reviewSaId   = null;
    private ?int $iotInProgId  = null;
    private ?int $updateIotId  = null;

    public function __construct()
    {
        $this->pendingQcId  = $this->resolveStatusId(self::STATUS_PENDING_QC);
        $this->pendingSaId  = $this->resolveStatusId(self::STATUS_PENDING_SA);
        $this->reviewQcId   = $this->resolveStatusId(self::STATUS_REVIEW_QC);
        $this->reviewSaId   = $this->resolveStatusId(self::STATUS_REVIEW_SA);
        $this->iotInProgId  = $this->resolveStatusId(self::STATUS_IOT_IN_PROG);
        $this->updateIotId  = $this->resolveStatusId(self::STATUS_UPDATE_IOT);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Check whether the incoming transition involves the IOT TCs parallel workflow.
     *
     * Called from ChangeRequestStatusService::processStatusUpdate() BEFORE the
     * normal workflow logic runs.
     *
     * Returns true  → caller should let this service handle the transition.
     * Returns false → normal workflow processing should continue.
     */
    public function isIotTcsTransition(int $crId, array $statusData): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $oldStatusId = (int) ($statusData['old_status_id'] ?? 0);
        $newStatusId = (int) ($statusData['new_status_id'] ?? 0);

        // We intercept transitions that originate from or target IOT-related statuses.
        // The "new_status_id" in statusData is the WORKFLOW ID, not the to-status-id, so
        // we resolve the to-status from the workflow record.
        $toStatusId = $this->resolveToStatusFromWorkflow($newStatusId);

        $isQcTransition = ($oldStatusId === $this->pendingQcId && $toStatusId === $this->reviewQcId);
        $isSaTransition = ($oldStatusId === $this->pendingSaId && $toStatusId === $this->reviewSaId);
        $isQcToIotInProgress = ($oldStatusId === $this->reviewQcId && $toStatusId === $this->iotInProgId);
        $isVendorToIotInProgress = ($oldStatusId === $this->reviewSaId && $toStatusId === $this->iotInProgId);
        $isQcToUpdateIot = ($oldStatusId === $this->reviewQcId && $toStatusId === $this->updateIotId);
        $isVendorToUpdateIot = ($oldStatusId === $this->reviewSaId && $toStatusId === $this->updateIotId);

        Log::info('IotTcsFlowService: isIotTcsTransition check', [
            'cr_id'          => $crId,
            'old_status_id'  => $oldStatusId,
            'to_status_id'   => $toStatusId,
            'is_qc'          => $isQcTransition,
            'is_sa'          => $isSaTransition,
            'is_qc_to_iot'   => $isQcToIotInProgress,
            'is_vendor_to_iot' => $isVendorToIotInProgress,
            'is_qc_to_update' => $isQcToUpdateIot,
            'is_vendor_to_update' => $isVendorToUpdateIot,
        ]);

        return $isQcTransition || $isSaTransition || $isQcToIotInProgress || $isVendorToIotInProgress || $isQcToUpdateIot || $isVendorToUpdateIot;
    }

    /**
     * Handle the IOT TCs transition.
     *
     * Must be called only when isIotTcsTransition() returned true.
     * Applies the correct independence / merge logic and returns the active flag
     * value ('0' or '1') that was set on the newly created record.
     */
    public function handleIotTcsTransition(int $crId, array $statusData, array $context = []): string
    {
        $oldStatusId = (int) ($statusData['old_status_id'] ?? 0);
        $newWorkflowId = (int) ($statusData['new_status_id'] ?? 0);
        $toStatusId    = $this->resolveToStatusFromWorkflow($newWorkflowId);

        if ($oldStatusId === $this->pendingQcId && $toStatusId === $this->reviewQcId) {
            return $this->handleQcTransition($crId, $statusData, $context);
        }

        if ($oldStatusId === $this->pendingSaId && $toStatusId === $this->reviewSaId) {
            return $this->handleSaTransition($crId, $statusData, $context);
        }

        if ($oldStatusId === $this->reviewQcId && $toStatusId === $this->iotInProgId) {
            return $this->handleQcToIotInProgressTransition($crId, $statusData, $context);
        }

        if ($oldStatusId === $this->reviewSaId && $toStatusId === $this->iotInProgId) {
            return $this->handleVendorToIotInProgressTransition($crId, $statusData, $context);
        }

        if ($oldStatusId === $this->reviewQcId && $toStatusId === $this->updateIotId) {
            return $this->handleQcToUpdateIotTransition($crId, $statusData, $context);
        }

        if ($oldStatusId === $this->reviewSaId && $toStatusId === $this->updateIotId) {
            return $this->handleVendorToUpdateIotTransition($crId, $statusData, $context);
        }

        // Fallback – should not reach here if isIotTcsTransition() was checked first
        Log::warning('IotTcsFlowService: handleIotTcsTransition called for unrecognised transition', [
            'cr_id'          => $crId,
            'old_status_id'  => $oldStatusId,
            'to_status_id'   => $toStatusId,
        ]);
        return '1';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PRIVATE HANDLERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Handle: Pending IOT TCs Review QC  →  IOT TCs Review QC
     *
     * This is the INDEPENDENT branch.
     *  1. Complete the current QC pending record (active → 2).
     *  2. Create IOT TCs Review QC with active=1 (always active, independent).
     *  3. Do NOT touch the SA branch record at all.
     *  4. NO merge logic - QC branch is completely independent.
     */
    private function handleQcTransition(int $crId, array $statusData, array $context): string
    {
        Log::info('IotTcsFlowService: Handling QC branch transition', ['cr_id' => $crId]);

        // 1. Complete the Pending QC record
        $this->completeStatusRecord($crId, $this->pendingQcId);

        // 2. Create IOT TCs Review QC — always active=1 (independent)
        $this->createStatusRecord($crId, $this->pendingQcId, $this->reviewQcId, '1', $context);

        Log::info('IotTcsFlowService: QC branch → IOT TCs Review QC created (active=1) - COMPLETELY INDEPENDENT', ['cr_id' => $crId]);

        // 3. No merge logic - QC branch is fully independent

        return '1';
    }

    /**
     * Handle: Pending IOT TCs Review SA  →  IOT TCs Review vendor
     *
     * This creates the IOT TCs Review vendor status as the active status.
     *  1. Complete the current SA pending record (active → 2).
     *  2. Create IOT TCs Review vendor with active=1 (this becomes the active status).
     *  3. Do NOT auto-transition to IOT In progress - let manual workflow handle that.
     */
    private function handleSaTransition(int $crId, array $statusData, array $context): string
    {
        Log::info('IotTcsFlowService: Handling SA branch transition', ['cr_id' => $crId]);

        // 1. Complete the Pending SA record
        $this->completeStatusRecord($crId, $this->pendingSaId);

        // 2. Create IOT TCs Review vendor as the active status (active=1)
        $this->createStatusRecord($crId, $this->pendingSaId, $this->reviewSaId, '1', $context);

        Log::info('IotTcsFlowService: SA branch → IOT TCs Review vendor created (active=1)', ['cr_id' => $crId]);

        // 3. Do NOT auto-transition to IOT In progress - keep IOT TCs Review vendor as active

        return '1';
    }

    /**
     * Handle: IOT TCs Review QC  →  IOT In progress
     *
     * This transitions from the QC review branch to the merged IOT In progress status.
     * Creates IOT In progress with active=0 if vendor review not completed,
     * or activates existing IOT In progress (active=0 to 1) if vendor review is completed.
     *  1. Complete the current QC review record (active → 2).
     *  2. Check if vendor review is also completed.
     *  3. If vendor completed, activate existing IOT In progress record (active=0 to 1).
     *  4. If vendor not completed, create IOT In progress with active=0.
     */
    private function handleQcToIotInProgressTransition(int $crId, array $statusData, array $context): string
    {
        Log::info('IotTcsFlowService: Handling QC to IOT In progress transition', ['cr_id' => $crId]);

        // 1. Complete the IOT TCs Review QC record
        $this->completeStatusRecord($crId, $this->reviewQcId);

        // 2. Check if vendor review is also completed
        $vendorReviewCompleted = $this->isStatusCompleted($crId, $this->reviewSaId);
        
        if ($vendorReviewCompleted) {
            // 3. Vendor completed - check if there's an inactive IOT In progress to activate
            if ($this->hasInactiveIotInProgress($crId)) {
                $this->activateIotInProgress($crId);
                Log::info('IotTcsFlowService: BOTH branches completed → IOT In progress activated (active=0 to 1)', ['cr_id' => $crId]);
                return '1';
            } else {
                // Create active IOT In progress if no inactive one exists (edge case)
                $this->createStatusRecord($crId, $this->reviewQcId, $this->iotInProgId, '1', $context);
                Log::info('IotTcsFlowService: BOTH branches completed → IOT In progress created (active=1)', ['cr_id' => $crId]);
                return '1';
            }
        } else {
            // 4. Vendor review not yet completed - create IOT In progress with active=0
            $this->createStatusRecord($crId, $this->reviewQcId, $this->iotInProgId, '0', $context);
            
            Log::info('IotTcsFlowService: QC completed, vendor not yet → IOT In progress created (active=0)', ['cr_id' => $crId]);
            return '0'; // Inactive status created
        }
    }

    /**
     * Handle: IOT TCs Review vendor  →  IOT In progress
     *
     * This transitions from the vendor review branch to the merged IOT In progress status.
     * Creates IOT In progress with active=0 if QC review not completed,
     * or activates existing IOT In progress (active=0 to 1) if QC review is completed.
     *  1. Complete the current vendor review record (active → 2).
     *  2. Check if QC review is also completed.
     *  3. If QC completed, activate existing IOT In progress record (active=0 to 1).
     *  4. If QC not completed, create IOT In progress with active=0.
     */
    private function handleVendorToIotInProgressTransition(int $crId, array $statusData, array $context): string
    {
        Log::info('IotTcsFlowService: Handling Vendor to IOT In progress transition', ['cr_id' => $crId]);

        // 1. Complete the IOT TCs Review vendor record
        $this->completeStatusRecord($crId, $this->reviewSaId);

        // 2. Check if QC review is also completed
        $qcReviewCompleted = $this->isStatusCompleted($crId, $this->reviewQcId);
        
        if ($qcReviewCompleted) {
            // 3. QC completed - check if there's an inactive IOT In progress to activate
            if ($this->hasInactiveIotInProgress($crId)) {
                $this->activateIotInProgress($crId);
                Log::info('IotTcsFlowService: BOTH branches completed → IOT In progress activated (active=0 to 1)', ['cr_id' => $crId]);
                return '1';
            } else {
                // Create active IOT In progress if no inactive one exists (edge case)
                $this->createStatusRecord($crId, $this->reviewSaId, $this->iotInProgId, '1', $context);
                Log::info('IotTcsFlowService: BOTH branches completed → IOT In progress created (active=1)', ['cr_id' => $crId]);
                return '1';
            }
        } else {
            // 4. QC review not yet completed - create IOT In progress with active=0
            $this->createStatusRecord($crId, $this->reviewSaId, $this->iotInProgId, '0', $context);
            
            Log::info('IotTcsFlowService: Vendor completed, QC not yet → IOT In progress created (active=0)', ['cr_id' => $crId]);
            return '0'; // Inactive status created
        }
    }

    /**
     * Handle: IOT TCs Review QC  →  Update IOT TCs
     *
     * When the CR status transitions to "Update IOT TCs":
     *  1. Complete the IOT TCs Review QC record (active → 2).
     *  2. Complete ALL other change_request_statuses rows for this CR that still
     *     have active=1 (vendor review, IOT In Progress, or any other parallel
     *     record) via a single generic sweep — ensuring no orphaned active rows remain.
     *  3. Create Update IOT TCs with active=1.
     */
    private function handleQcToUpdateIotTransition(int $crId, array $statusData, array $context): string
    {
        Log::info('IotTcsFlowService: Handling QC to Update IOT TCs transition', ['cr_id' => $crId]);

        // 1. Complete the IOT TCs Review QC record
        $this->completeStatusRecord($crId, $this->reviewQcId);

        // 2. Complete ALL other active=1 records on this CR (vendor review, IOT In Progress, etc.)
        $this->completeAllOtherActiveStatuses($crId, $this->reviewQcId);

        // 3. Create Update IOT TCs with active=1
        $this->createStatusRecord($crId, $this->reviewQcId, $this->updateIotId, '1', $context);

        Log::info('IotTcsFlowService: QC branch → Update IOT TCs created (active=1)', ['cr_id' => $crId]);

        return '1';
    }

    /**
     * Handle: IOT TCs Review vendor  →  Update IOT TCs
     *
     * When the CR status transitions to "Update IOT TCs":
     *  1. Complete the IOT TCs Review vendor record (active → 2).
     *  2. Complete ALL other change_request_statuses rows for this CR that still
     *     have active=1 (QC review, IOT In Progress, or any other parallel
     *     record) via a single generic sweep — ensuring no orphaned active rows remain.
     *  3. Create Update IOT TCs with active=1.
     */
    private function handleVendorToUpdateIotTransition(int $crId, array $statusData, array $context): string
    {
        Log::info('IotTcsFlowService: Handling Vendor to Update IOT TCs transition', ['cr_id' => $crId]);

        // 1. Complete the IOT TCs Review vendor record
        $this->completeStatusRecord($crId, $this->reviewSaId);

        // 2. Complete ALL other active=1 records on this CR (QC review, IOT In Progress, etc.)
        $this->completeAllOtherActiveStatuses($crId, $this->reviewSaId);

        // 3. Create Update IOT TCs with active=1
        $this->createStatusRecord($crId, $this->reviewSaId, $this->updateIotId, '1', $context);

        Log::info('IotTcsFlowService: Vendor branch → Update IOT TCs created (active=1)', ['cr_id' => $crId]);

        return '1';
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DB HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Mark the most-recent active record for $statusId as completed (active=2).
     */
    private function completeStatusRecord(int $crId, int $statusId): void
    {
        $updated = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $statusId)
            ->where('active', '1')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->update(['active' => '2']);

        Log::info('IotTcsFlowService: completeStatusRecord', [
            'cr_id'     => $crId,
            'status_id' => $statusId,
            'updated'   => $updated,
        ]);
    }

    /**
     * Complete ALL change_request_statuses records for $crId where active=1,
     * EXCEPT for the record already handled as $exceptStatusId.
     *
     * Used when transitioning to "Update IOT TCs" to guarantee no stale
     * active=1 rows remain on the CR, regardless of which parallel branch
     * initiated the transition or which other statuses may exist.
     */
    private function completeAllOtherActiveStatuses(int $crId, int $exceptStatusId): void
    {
        $updated = ChangeRequestStatus::where('cr_id', $crId)
            ->where('active', '1')
            ->where('new_status_id', '!=', $exceptStatusId)
            ->update(['active' => '2']);

        Log::info('IotTcsFlowService: completeAllOtherActiveStatuses', [
            'cr_id'            => $crId,
            'except_status_id' => $exceptStatusId,
            'rows_updated'     => $updated,
        ]);
    }

    /**
     * Check if the most recent record for $statusId is completed (active=2).
     */
    private function isStatusCompleted(int $crId, int $statusId): bool
    {
        $mostRecentRecord = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $statusId)
            ->orderBy('id', 'desc')
            ->first();

        $isCompleted = $mostRecentRecord && $mostRecentRecord->active == '2';

        Log::info('IotTcsFlowService: isStatusCompleted', [
            'cr_id'       => $crId,
            'status_id'   => $statusId,
            'most_recent_id' => $mostRecentRecord ? $mostRecentRecord->id : null,
            'most_recent_active' => $mostRecentRecord ? $mostRecentRecord->active : null,
            'is_completed' => $isCompleted,
        ]);

        return $isCompleted;
    }

    /**
     * Check if most recent record for $statusId is active (active=1).
     */
    private function isStatusActive(int $crId, int $statusId): bool
    {
        $activeRecord = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $statusId)
            ->where('active', '1')
            ->orderBy('id', 'desc')
            ->first();

        $isActive = $activeRecord !== null;

        Log::info('IotTcsFlowService: isStatusActive', [
            'cr_id'      => $crId,
            'status_id'  => $statusId,
            'is_active'  => $isActive,
        ]);

        return $isActive;
    }

    /**
     * Check if there's an inactive IOT In progress record (active=0) that can be activated.
     */
    private function hasInactiveIotInProgress(int $crId): bool
    {
        $inactiveRecord = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $this->iotInProgId)
            ->where('active', '0')
            ->orderBy('id', 'desc')
            ->first();

        $hasInactive = $inactiveRecord !== null;

        Log::info('IotTcsFlowService: hasInactiveIotInProgress', [
            'cr_id'      => $crId,
            'has_inactive' => $hasInactive,
        ]);

        return $hasInactive;
    }

    /**
     * Activate the most recent inactive IOT In progress record (active=0 to 1).
     */
    private function activateIotInProgress(int $crId): void
    {
        $updated = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $this->iotInProgId)
            ->where('active', '0')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->update(['active' => '1']);

        Log::info('IotTcsFlowService: activateIotInProgress', [
            'cr_id'     => $crId,
            'updated'   => $updated,
        ]);
    }

    /**
     * Create a new change_request_statuses record.
     *
     * Copies group/user context from the existing record of $fromStatusId when available,
     * falling back to context values passed in.
     */
    private function createStatusRecord(
        int $crId,
        int $fromStatusId,
        int $toStatusId,
        string $active,
        array $context
    ): void {
        // Derive group information from the most-recent status record of fromStatus
        $template = ChangeRequestStatus::where('cr_id', $crId)
            ->where('new_status_id', $fromStatusId)
            ->orderBy('id', 'desc')
            ->first();

        $toStatus = Status::find($toStatusId);
        $sla      = $toStatus ? (int) $toStatus->sla : 0;

        $userId           = $context['user_id']           ?? ($template->user_id ?? \Auth::id());
        $currentGroupId   = $context['current_group_id']  ?? ($template->current_group_id ?? null);
        $previousGroupId  = $context['previous_group_id'] ?? ($template->current_group_id ?? null);
        $referenceGroupId = $context['reference_group_id'] ?? ($template->reference_group_id ?? null);

        // Resolve the view group for the target status if possible
        if (isset($context['application_id']) && $toStatus) {
            $viewGroup = $toStatus->GetViewGroup($context['application_id']);
            if ($viewGroup) {
                $currentGroupId = $viewGroup->id;
            }
        }

        ChangeRequestStatus::create([
            'cr_id'              => $crId,
            'old_status_id'      => $fromStatusId,
            'new_status_id'      => $toStatusId,
            'group_id'           => null,
            'reference_group_id' => $referenceGroupId,
            'previous_group_id'  => $previousGroupId,
            'current_group_id'   => $currentGroupId,
            'user_id'            => $userId,
            'sla'                => $sla,
            'active'             => $active,
            'created_at'         => now(),
            'updated_at'         => null,
        ]);

        Log::info('IotTcsFlowService: createStatusRecord', [
            'cr_id'         => $crId,
            'from_status'   => $fromStatusId,
            'to_status'     => $toStatusId,
            'active'        => $active,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // UTILITY
    // ═══════════════════════════════════════════════════════════════════════════

    private function isConfigured(): bool
    {
        return $this->pendingQcId  !== null
            && $this->pendingSaId  !== null
            && $this->reviewQcId   !== null
            && $this->reviewSaId   !== null
            && $this->iotInProgId  !== null
            && $this->updateIotId  !== null;
    }

    private function resolveStatusId(string $statusName): ?int
    {
        $status = Status::where('status_name', $statusName)
            ->where('active', '1')
            ->first();

        if (!$status) {
            Log::warning('IotTcsFlowService: Status not found in DB', ['status_name' => $statusName]);
        }

        return $status?->id;
    }

    /**
     * Given a workflow ID (new_workflow_id / new_status_id from the request),
     * resolve the actual to_status_id from new_workflow_statuses.
     */
    private function resolveToStatusFromWorkflow(int $workflowId): ?int
    {
        $workflow = NewWorkFlow::with('workflowstatus')->find($workflowId);

        if (!$workflow || $workflow->workflowstatus->isEmpty()) {
            return null;
        }

        return (int) $workflow->workflowstatus->first()->to_status_id;
    }
}