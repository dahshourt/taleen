<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChangeRequestGroupAssignmentService
 *
 * Handles special group-assignment overrides during CR status transitions.
 *
 * Rule (no DB column required – detected purely from workflow data):
 *
 *   Current status  = "Pending Operation DM and Capacity Approval"
 *   Workflow type   = "In House"
 *   Next status     = "Application Support Production Deployment Pre-requisites"
 *                     (existence itself proves app_support is active)
 *   → Assign group "CR Team Admin" instead of "Application Support"
 *
 * The old app_support = 1 column check has been replaced by verifying that
 * the next status IS the Application Support Pre-requisites status, which
 * is only reachable on CRs that follow the Application Support path.
 */
class ChangeRequestGroupAssignmentService
{
    const STATUS_PENDING_OP_DM_CAPACITY  = 'Pending Operation DM and Capacity Approval';
    const STATUS_APP_SUPPORT_PROD_PREREQ = 'Application Support Production Deployment Pre-requisites';
    const GROUP_APPLICATION_SUPPORT      = 'Application Support';
    const GROUP_CR_TEAM_ADMIN            = 'CR Team Admin';
    const WORKFLOW_TYPE_IN_HOUSE         = 'In House';

    /**
     * Resolve the group that should be assigned when a CR transitions to
     * $nextStatusId.
     *
     * Returns the overridden group ID when all conditions are met,
     * or null to fall back to the default group-resolution logic.
     *
     * Condition chain (all must pass):
     *   1. Current status name  = STATUS_PENDING_OP_DM_CAPACITY
     *   2. CR workflow type     = WORKFLOW_TYPE_IN_HOUSE
     *   3. Next status name     = STATUS_APP_SUPPORT_PROD_PREREQ
     *      (presence in the workflow proves the app_support path is active
     *       — no separate DB flag required)
     *
     * @param  int   $crId
     * @param  int   $currentStatusId
     * @param  int   $nextStatusId
     * @return int|null  CR Team Admin group ID, or null
     */
    public function resolveGroupOverride(
        int $crId,
        int $currentStatusId,
        int $nextStatusId
    ): ?int {
        try {
            // ------------------------------------------------------------------
            // 1. Current status must be "Pending Operation DM and Capacity Approval"
            // ------------------------------------------------------------------
            $currentStatusName = DB::table('statuses')
                ->where('id', $currentStatusId)
                ->value('status_name');

            if ($currentStatusName !== self::STATUS_PENDING_OP_DM_CAPACITY) {
                return null;
            }

            // ------------------------------------------------------------------
            // 2. Load CR – must exist
            // ------------------------------------------------------------------
            $cr = DB::table('change_request')
                ->where('id', $crId)
                ->first(['id', 'workflow_type_id']);

            if (!$cr) {
                Log::warning("[GroupAssignment] CR #{$crId} not found.");
                return null;
            }

            // ------------------------------------------------------------------
            // 3. Workflow type must be "In House"
            // ------------------------------------------------------------------
            $workflowTypeName = DB::table('workflow_type')
                ->where('id', $cr->workflow_type_id)
                ->value('name');

            if ($workflowTypeName !== self::WORKFLOW_TYPE_IN_HOUSE) {
                return null;
            }

            // ------------------------------------------------------------------
            // 4. Next status must be "Application Support Production Deployment
            //    Pre-requisites" — this replaces the old app_support = 1 check.
            //    If the workflow has routed to this status it means the CR is
            //    following the Application Support path; no extra flag needed.
            // ------------------------------------------------------------------
            $nextStatusName = DB::table('statuses')
                ->where('id', $nextStatusId)
                ->value('status_name');

            if ($nextStatusName !== self::STATUS_APP_SUPPORT_PROD_PREREQ) {
                return null;
            }

            // ------------------------------------------------------------------
            // 5. All conditions met — resolve "CR Team Admin" group ID
            // ------------------------------------------------------------------
            $crTeamAdminGroupId = DB::table('groups')
                ->where('name', self::GROUP_CR_TEAM_ADMIN)
                ->value('id');

            if (!$crTeamAdminGroupId) {
                Log::error(
                    '[GroupAssignment] Group "' . self::GROUP_CR_TEAM_ADMIN
                    . '" not found in groups table. Cannot override assignment.'
                );
                return null;
            }

            Log::info(
                "[GroupAssignment] Override triggered for CR #{$crId}: "
                . "assigning '" . self::GROUP_CR_TEAM_ADMIN
                . "' (id={$crTeamAdminGroupId}) instead of '"
                . self::GROUP_APPLICATION_SUPPORT . "'."
            );

            return (int) $crTeamAdminGroupId;

        } catch (\Throwable $e) {
            Log::error(
                '[GroupAssignment] Unexpected error: ' . $e->getMessage(),
                ['cr_id' => $crId, 'exception' => $e]
            );
            return null;
        }
    }

    /**
     * Convenience boolean check — true when the override will fire.
     */
    public function shouldOverride(
        int $crId,
        int $currentStatusId,
        int $nextStatusId
    ): bool {
        return $this->resolveGroupOverride($crId, $currentStatusId, $nextStatusId) !== null;
    }
}
