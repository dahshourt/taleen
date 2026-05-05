<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChangeRequestStatusService
 *
 * Handles status transitions for Change Requests, including group assignment
 * resolution (with special-case overrides via ChangeRequestGroupAssignmentService).
 */
class ChangeRequestStatusService
{
    /**
     * @var ChangeRequestGroupAssignmentService
     */
    protected ChangeRequestGroupAssignmentService $groupAssignmentService;

    public function __construct(
        ChangeRequestGroupAssignmentService $groupAssignmentService
    ) {
        $this->groupAssignmentService = $groupAssignmentService;
    }

    // -----------------------------------------------------------------------

    /**
     * Transition a CR to $nextStatusId.
     *
     * Resolves the assigned group by:
     *   1. Checking for a special-case override (e.g. In-House + app_support).
     *   2. Falling back to the default group_statuses lookup.
     *
     * @param  int      $crId
     * @param  int      $currentStatusId
     * @param  int      $nextStatusId
     * @param  int      $actingUserId
     * @return bool
     */
    public function transitionStatus(
        int $crId,
        int $currentStatusId,
        int $nextStatusId,
        int $actingUserId
    ): bool {
        DB::beginTransaction();
        try {
            // ----------------------------------------------------------------
            // 1. Resolve the group to assign
            // ----------------------------------------------------------------
            $groupId = $this->resolveAssignedGroup(
                $crId,
                $currentStatusId,
                $nextStatusId
            );

            // ----------------------------------------------------------------
            // 2. Deactivate current active status record
            // ----------------------------------------------------------------
            DB::table('change_request_statuses')
                ->where('cr_id', $crId)
                ->where('active', '1')
                ->update([
                    'active'     => '0',
                    'updated_at' => now(),
                ]);

            // ----------------------------------------------------------------
            // 3. Insert new status record
            // ----------------------------------------------------------------
            DB::table('change_request_statuses')->insert([
                'cr_id'              => $crId,
                'old_status_id'      => $currentStatusId,
                'new_status_id'      => $nextStatusId,
                'user_id'            => $actingUserId,
                'active'             => '1',
                'assignment_user_id' => null,
                'sla'                => 0,
                'sla_dif'            => 0,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // ----------------------------------------------------------------
            // 4. Update the CR's current group and status
            // ----------------------------------------------------------------
            $updatePayload = [
                'status_id'  => $nextStatusId,
                'updated_at' => now(),
            ];

            if ($groupId !== null) {
                $updatePayload['group_id'] = $groupId;
            }

            DB::table('change_request')
                ->where('id', $crId)
                ->update($updatePayload);

            // ----------------------------------------------------------------
            // 5. Log the transition
            // ----------------------------------------------------------------
            $groupName = $groupId
                ? DB::table('groups')->where('id', $groupId)->value('name')
                : 'unchanged';

            Log::info(
                "[StatusTransition] CR #{$crId}: status {$currentStatusId} "
                . "→ {$nextStatusId}, group assigned: {$groupName} (id={$groupId})."
            );

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(
                '[StatusTransition] Failed for CR #' . $crId . ': '
                . $e->getMessage(),
                ['exception' => $e]
            );
            return false;
        }
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Determine which group_id should be assigned for the given transition.
     *
     * Priority order:
     *   1. Special-case override (ChangeRequestGroupAssignmentService)
     *   2. Default: first group linked to $nextStatusId in group_statuses
     *      where type = 1 (primary assignee)
     *
     * @param  int   $crId
     * @param  int   $currentStatusId
     * @param  int   $nextStatusId
     * @return int|null
     */
    protected function resolveAssignedGroup(
        int $crId,
        int $currentStatusId,
        int $nextStatusId
    ): ?int {
        // --- 1. Special-case override ---
        $overrideGroupId = $this->groupAssignmentService->resolveGroupOverride(
            $crId,
            $currentStatusId,
            $nextStatusId
        );

        if ($overrideGroupId !== null) {
            return $overrideGroupId;
        }

        // --- 2. Default lookup from group_statuses ---
        $defaultGroupId = DB::table('group_statuses')
            ->where('status_id', $nextStatusId)
            ->where('type', 1)        // type 1 = primary assignee
            ->value('group_id');

        return $defaultGroupId ? (int) $defaultGroupId : null;
    }
}
