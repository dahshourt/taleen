<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChangeRequestStatusService
 *
 * Handles CR status transitions and group-assignment resolution.
 * No database schema changes are required — all override logic is
 * derived from existing workflow / status / group data.
 */
class ChangeRequestStatusService
{
    protected ChangeRequestGroupAssignmentService $groupAssignmentService;

    public function __construct(ChangeRequestGroupAssignmentService $groupAssignmentService)
    {
        $this->groupAssignmentService = $groupAssignmentService;
    }

    /**
     * Transition CR $crId from $currentStatusId to $nextStatusId.
     *
     * Group resolution order:
     *   1. Special-case override  (ChangeRequestGroupAssignmentService)
     *   2. Default: group_statuses lookup  (type = 1 = primary assignee)
     *
     * @param  int   $crId
     * @param  int   $currentStatusId
     * @param  int   $nextStatusId
     * @param  int   $actingUserId
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
            // 1. Resolve group (override or default — pure code, no extra columns)
            $groupId = $this->resolveAssignedGroup($crId, $currentStatusId, $nextStatusId);

            // 2. Deactivate current active status record
            DB::table('change_request_statuses')
                ->where('cr_id', $crId)
                ->where('active', '1')
                ->update(['active' => '0', 'updated_at' => now()]);

            // 3. Insert new status record
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

            // 4. Update CR row (status + optionally group)
            $updatePayload = ['status_id' => $nextStatusId, 'updated_at' => now()];
            if ($groupId !== null) {
                $updatePayload['group_id'] = $groupId;
            }
            DB::table('change_request')->where('id', $crId)->update($updatePayload);

            // 5. Log
            $groupName = $groupId
                ? DB::table('groups')->where('id', $groupId)->value('name')
                : 'unchanged';

            Log::info(
                "[StatusTransition] CR #{$crId}: "
                . "{$currentStatusId} -> {$nextStatusId}, "
                . "group: {$groupName} (id={$groupId})."
            );

            DB::commit();
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(
                '[StatusTransition] Failed for CR #' . $crId . ': ' . $e->getMessage(),
                ['exception' => $e]
            );
            return false;
        }
    }

    /**
     * Determine which group_id to assign for this transition.
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
        // Priority 1: special-case override (code-only, no DB flag)
        $overrideGroupId = $this->groupAssignmentService->resolveGroupOverride(
            $crId,
            $currentStatusId,
            $nextStatusId
        );

        if ($overrideGroupId !== null) {
            return $overrideGroupId;
        }

        // Priority 2: default — look up primary group for the target status
        $defaultGroupId = DB::table('group_statuses')
            ->where('status_id', $nextStatusId)
            ->where('type', 1)
            ->value('group_id');

        return $defaultGroupId ? (int) $defaultGroupId : null;
    }
}
