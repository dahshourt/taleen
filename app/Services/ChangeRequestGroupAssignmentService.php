<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChangeRequestGroupAssignmentService
 *
 * Handles special group-assignment overrides during CR status transitions.
 *
 * Rule implemented here:
 *   When the CURRENT status is "Pending Operation DM and Capacity Approval"
 *   AND the CR has app_support = 1
 *   AND the workflow type is "In House"
 *   → Check whether the NEXT status is
 *     "Application Support Production Deployment Pre-requisites".
 *   If it exists, assign the group to "CR Team Admin" instead of
 *   "Application Support".
 */
class ChangeRequestGroupAssignmentService
{
    // -----------------------------------------------------------------------
    // Status / group name constants  (names as stored in the DB)
    // -----------------------------------------------------------------------
    const STATUS_PENDING_OP_DM_CAPACITY =
        'Pending Operation DM and Capacity Approval';

    const STATUS_APP_SUPPORT_PROD_PREREQ =
        'Application Support Production Deployment Pre-requisites';

    const GROUP_APPLICATION_SUPPORT = 'Application Support';
    const GROUP_CR_TEAM_ADMIN       = 'CR Team Admin';

    const WORKFLOW_TYPE_IN_HOUSE    = 'In House';

    // -----------------------------------------------------------------------

    /**
     * Resolve the group that should be assigned when a CR transitions to the
     * given $nextStatusId.
     *
     * Returns the overridden group ID when all conditions are met, otherwise
     * returns null (meaning: use the default group resolution logic).
     *
     * @param  int   $crId           ID of the change_request row
     * @param  int   $currentStatusId  ID of the status the CR is leaving
     * @param  int   $nextStatusId     ID of the status the CR is entering
     * @return int|null  Overridden group ID, or null for default behaviour
     */
    public function resolveGroupOverride(
        int $crId,
        int $currentStatusId,
        int $nextStatusId
    ): ?int {
        try {
            // 1. Verify current status name
            $currentStatus = DB::table('statuses')
                ->where('id', $currentStatusId)
                ->value('status_name');

            if ($currentStatus !== self::STATUS_PENDING_OP_DM_CAPACITY) {
                return null; // Rule does not apply
            }

            // 2. Load the CR and check app_support flag and workflow type
            $cr = DB::table('change_request')
                ->where('id', $crId)
                ->first();

            if (!$cr) {
                Log::warning(
                    "[GroupAssignment] CR #{$crId} not found."
                );
                return null;
            }

            // app_support must be 1
            if ((int) ($cr->app_support ?? 0) !== 1) {
                return null;
            }

            // Workflow type must be "In House"
            $workflowTypeName = DB::table('workflow_type')
                ->where('id', $cr->workflow_type_id)
                ->value('name');

            if ($workflowTypeName !== self::WORKFLOW_TYPE_IN_HOUSE) {
                return null;
            }

            // 3. Verify that the NEXT status is the App Support Prod Pre-req status
            $nextStatusName = DB::table('statuses')
                ->where('id', $nextStatusId)
                ->value('status_name');

            if ($nextStatusName !== self::STATUS_APP_SUPPORT_PROD_PREREQ) {
                return null;
            }

            // 4. All conditions met – look up the "CR Team Admin" group ID
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
                . "assigning group '" . self::GROUP_CR_TEAM_ADMIN
                . "' (id={$crTeamAdminGroupId}) instead of '"
                . self::GROUP_APPLICATION_SUPPORT . "'."
            );

            return (int) $crTeamAdminGroupId;

        } catch (\Throwable $e) {
            Log::error(
                '[GroupAssignment] Unexpected error in resolveGroupOverride: '
                . $e->getMessage(),
                ['cr_id' => $crId, 'exception' => $e]
            );
            return null; // Fall back to default behaviour on error
        }
    }

    // -----------------------------------------------------------------------
    // Convenience helper
    // -----------------------------------------------------------------------

    /**
     * Return true when the override rule applies for the given transition.
     * Useful for callers that only need a boolean check.
     */
    public function shouldOverride(
        int $crId,
        int $currentStatusId,
        int $nextStatusId
    ): bool {
        return $this->resolveGroupOverride($crId, $currentStatusId, $nextStatusId) !== null;
    }
}
