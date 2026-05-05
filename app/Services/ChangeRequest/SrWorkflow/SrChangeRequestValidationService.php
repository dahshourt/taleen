<?php

namespace App\Services\ChangeRequest\SrWorkflow;

use App\Http\Repository\ChangeRequest\ChangeRequestStatusRepository;
use App\Models\Change_request;
use App\Models\Change_request_statuse as ChangeRequestStatus;
use App\Models\NewWorkFlow;
use App\Models\Status;
use App\Models\SrTechnicalCr;
use App\Models\SrTechnicalCrTeam;
use App\Services\ChangeRequest\ChangeRequestStatusService;
use App\Traits\ChangeRequest\ChangeRequestConstants;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Events\ChangeRequestStatusUpdated;

/**
 * Handles technical team validation for SR Workflow (type 15).
 *
 * Mirrors the logic from ChangeRequestValidationService::handleTechnicalTeamValidation
 * but uses view_sr_technical_team_flag instead of view_technical_team_flag,
 * and reads sr_technical_teams from the request instead of technical_teams.
 */
class SrChangeRequestValidationService
{
    use ChangeRequestConstants;

    private const ACTIVE_STATUS = '1';
    private const INACTIVE_STATUS = '0';
    private const COMPLETED_STATUS = '2';

    private $active_flag = '0';

    public static array $ACTIVE_STATUS_ARRAY = [self::ACTIVE_STATUS, 1];
    public static array $INACTIVE_STATUS_ARRAY = [self::INACTIVE_STATUS, 0];
    public static array $COMPLETED_STATUS_ARRAY = [self::COMPLETED_STATUS, 2];

    /**
     * Handle SR technical team validation workflow.
     *
     * This is the SR equivalent of ChangeRequestValidationService::handleTechnicalTeamValidation.
     * It checks view_sr_technical_team_flag on the old status and routes
     * the CR through the SR technical team approval process.
     *
     * @param int   $id      Change request ID
     * @param mixed $request The update request
     * @return bool  True if the update should stop here (waiting for other teams), false to continue
     */
    public function handleSrTechnicalTeamValidation($id, $request): bool
    {

        $statusService = new ChangeRequestStatusService();
        $statusData = $statusService->extractStatusData($request);

        $newStatusId = $request->new_status_id ?? null;
        $oldStatusId = $request->old_status_id ?? null;

        if (!$newStatusId || !$oldStatusId) {
            return false;
        }

        $workflow = NewWorkFlow::find($newStatusId);
        $oldStatusData = Status::find($oldStatusId);

        // Key difference: check view_sr_technical_team_flag instead of view_technical_team_flag
        $fromStatusName = config('change_request.sr_special_transitions.from');

        if (!$oldStatusData || (!$oldStatusData->view_sr_technical_team_flag && $oldStatusData->status_name != $fromStatusName)) {
            return false;
        }

        $technicalDefaultGroup = session('default_group') ?: auth()->user()->default_group;
        $cr = Change_request::find($id);
        // Key difference: use SrTechnicalCr instead of TechnicalCr
        $technicalCr = SrTechnicalCr::where('cr_id', $id)->orderBy('id', 'desc')->first();
        //dd($technicalCr);
        if (!$technicalCr || $technicalCr->status != '0') {
            return false;
        }

        $srUpdateService = new SrChangeRequestUpdateService();
        $srUpdateService->mirrorSrCrStatusToTechStreams($id, (int) $workflow->workflowstatus[0]->to_status_id, null, 'actor');

        $result = $this->processSrTechnicalTeamStatus($technicalCr, $oldStatusData, $workflow, $technicalDefaultGroup, $request);

        event(new ChangeRequestStatusUpdated($cr, $statusData, $request, $this->active_flag));

        return $result;
    }

    /**
     * Process SR technical team status based on workflow.
     *
     * Mirrors ChangeRequestValidationService::processTechnicalTeamStatus
     * but checks view_sr_technical_team_flag when determining if the next
     * status is also an SR technical flag status.
     *
     * @param SrTechnicalCr $technicalCr
     * @param Status      $oldStatusData
     * @param NewWorkFlow $workflow
     * @param int         $group
     * @param mixed       $request
     * @return bool
     */
    protected function processSrTechnicalTeamStatus($technicalCr, $oldStatusData, $workflow, $group, $request): bool
    {
        $statusIds = $this->getStatusIds();
        $toStatusData = NewWorkFlow::find($request->new_status_id);
        $globalParkedIds = array_values(config('change_request.parked_status_ids', []));
        $srParkedIds = array_values(config('change_request.sr_parked_status_ids', []));
        $parkedIds = $srParkedIds;

        $promo_unparked_ids = array_values(config('change_request.promo_unparked_ids', []));

        $this->updateCurrentStatusByGroup($technicalCr->cr_id, $oldStatusData->toArray(), $group);

        if (in_array($toStatusData->workflowstatus[0]->to_status->status_name, $parkedIds, true)) {
            $checkWorkflowType = NewWorkFlow::find($request->new_status_id)->workflow_type;

            if ($checkWorkflowType) { // reject
                $technicalCr->status = '2';
                $technicalCr->save();
                $technicalCr->sr_technical_cr_team()->where('group_id', $group)->update(['status' => '2']);
                foreach ($technicalCr->sr_technical_cr_team->pluck('group_id')->toArray() as $key => $groupId) {
                    if ($groupId != $group) {
                        $this->updateCurrentStatusByGroup($technicalCr->cr_id, $oldStatusData->toArray(), $groupId);
                    }
                }
            } else { // approve
                $technicalCr->sr_technical_cr_team()->where('group_id', $group)->update(['status' => '1']);

                $countAllTeams = $technicalCr->sr_technical_cr_team->count();
                $countApprovedTeams = $technicalCr->sr_technical_cr_team()->whereRaw('CAST(status AS CHAR) = ?', ['1'])->count();
                if ($countAllTeams > $countApprovedTeams) {
                    return true; // Still waiting for other teams
                }
                $technicalCr->status = '1';
                $technicalCr->save();
            }

            return false;
        }

        // Handle if next status is also an SR technical flag status
        // Key difference: check view_sr_technical_team_flag
        if ($toStatusData->workflowstatus[0]->to_status->view_sr_technical_team_flag) {

            $fromStatusName = config('change_request.sr_special_transitions.from');
            $toStatusName = config('change_request.sr_special_transitions.to');
            $newStatusRow = Status::find($workflow->workflowstatus[0]->to_status_id);
            $isSpecialTransition = ($oldStatusData && $oldStatusData->status_name === $fromStatusName &&
                $newStatusRow && $newStatusRow->status_name === $toStatusName);

            if ($isSpecialTransition) {
                $changeRequest = Change_request::find($technicalCr->cr_id);
                $statusService = new ChangeRequestStatusService();
                $statusData = $statusService->extractStatusData($request);
                $workflow = $this->getWorkflow($statusData);
                $this->handleSrSpecialTransition($changeRequest, $statusData, $workflow);
                return true;
            }

            $skipTeamUpdateStatuses = config('change_request.sr_technical_skip_team_update', []);
            $shouldSkipTeamUpdate = $oldStatusData && in_array($oldStatusData->status_name, $skipTeamUpdateStatuses);

            if (!$shouldSkipTeamUpdate) {
                $technicalCr->sr_technical_cr_team()->where('group_id', $group)->update(['status' => '1']);

                SrTechnicalCrTeam::create([
                    'group_id' => $group,
                    'technical_cr_id' => $technicalCr->id,
                    'current_status_id' => $workflow->workflowstatus[0]->to_status_id,
                    'status' => '0',
                ]);
            }
            $newStatusRow = Status::find($workflow->workflowstatus[0]->to_status_id);
            $previous_group_id = session('current_group') ?: auth()->user()->default_group;

            $payload = $this->buildStatusData(
                $technicalCr->cr_id,
                $request->old_status_id,
                (int) $workflow->workflowstatus[0]->to_status_id,
                $group,
                (int) $group,
                (int) $previous_group_id,
                (int) $group,
                Auth::id(),
                '1'
            );
            $this->active_flag = '1';
            $statusRepository = new ChangeRequestStatusRepository();
            $statusRepository->create($payload);

            return true;
        }

        // No need to wait for other teams
        $checkWorkflowType = NewWorkFlow::find($request->new_status_id)->workflow_type;
        if ($checkWorkflowType) { // reject
            $technicalCr->status = '2';
            $technicalCr->save();
            $technicalCr->sr_technical_cr_team()->where('group_id', $group)->update(['status' => '2']);
            foreach ($technicalCr->sr_technical_cr_team->pluck('group_id')->toArray() as $key => $groupId) {
                if ($groupId != $group) {
                    $this->updateCurrentStatusByGroup($technicalCr->cr_id, $oldStatusData->toArray(), $groupId);
                }
            }
        } else {
            if (in_array($toStatusData->workflowstatus[0]->to_status->id, $promo_unparked_ids, true)) {
                $skipTeamUpdateStatuses = config('change_request.sr_technical_skip_team_update', []);
                $shouldSkipTeamUpdate = $oldStatusData && in_array($oldStatusData->status_name, $skipTeamUpdateStatuses);

                if (!$shouldSkipTeamUpdate) {
                    $technicalCr->sr_technical_cr_team()->where('group_id', $group)->update(['status' => '1']);
                }
                $newStatusRow = Status::find($workflow->workflowstatus[0]->to_status_id);
                $previous_group_id = session('current_group') ?: auth()->user()->default_group;
                $payload = $this->buildStatusData(
                    $technicalCr->cr_id,
                    $request->old_status_id,
                    (int) $workflow->workflowstatus[0]->to_status_id,
                    null,
                    (int) $group,
                    (int) $previous_group_id,
                    (int) $newStatusRow->group_statuses->where('type', '2')->pluck('group_id')->toArray()[0],
                    Auth::id(),
                    '1'
                );
                $this->active_flag = '1';
                $statusRepository = new ChangeRequestStatusRepository();
                $statusRepository->create($payload);

                return true;
            }
            $skipTeamUpdateStatuses = config('change_request.sr_technical_skip_team_update', []);
            $shouldSkipTeamUpdate = $oldStatusData && in_array($oldStatusData->status_name, $skipTeamUpdateStatuses);

            if (!$shouldSkipTeamUpdate) {
                $technicalCr->sr_technical_cr_team()->where('group_id', $group)->update(['status' => '1']);
            }
            $countAllTeams = $technicalCr->sr_technical_cr_team->count();
            $countApprovedTeams = $technicalCr->sr_technical_cr_team()->whereRaw('CAST(status AS CHAR) = ?', ['1'])->count();
            if ($countAllTeams == $countApprovedTeams) {
                $technicalCr->status = '1';
                $technicalCr->save();
            }
        }

        return false;
    }

    /**
     * Update the current status record for a specific group.
     */
    private function updateCurrentStatusByGroup(int $changeRequestId, array $statusData, int $groupId): void
    {
        $currentStatus = ChangeRequestStatus::where('cr_id', $changeRequestId)
            ->where('new_status_id', $statusData['id'])
            ->where('group_id', $groupId)
            ->active()
            ->first();

        if (!$currentStatus) {
            Log::warning('SR Workflow: Current status not found for update', [
                'cr_id' => $changeRequestId,
                'group_id' => $groupId,
            ]);
            return;
        }

        $slaDifference = $this->calculateSlaDifference($currentStatus->created_at);
        $currentStatus->update([
            'sla_dif' => $slaDifference,
            'active' => '2',
        ]);
    }

    /**
     * Calculate SLA difference in days.
     */
    private function calculateSlaDifference(string $createdAt): int
    {
        return Carbon::parse($createdAt)->diffInDays(Carbon::now());
    }

    /**
     * Build status data array.
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
            'active' => $active,
        ];
    }

    private function handleSrSpecialTransition(Change_request $changeRequest, array $statusData, NewWorkFlow $workflow): void
    {
        // 1. Find the current record for the old status to get previous_group_id
        $currentRecord = ChangeRequestStatus::where('cr_id', $changeRequest->id)
            ->where('new_status_id', $statusData['old_status_id'])
            ->orderBy('id', 'desc')
            ->first();

        $previousGroupId = $currentRecord ? $currentRecord->previous_group_id : (session('current_group') ?: 1);
        $oldCurrentGroupId = $currentRecord ? $currentRecord->current_group_id : (session('current_group') ?: 1);

        if (request()->reference_status) {
            $currentStatus = ChangeRequestStatus::find(request()->reference_status);
        } else {
            $currentStatus = ChangeRequestStatus::where('cr_id', $changeRequest->id)
                ->where('new_status_id', $statusData['old_status_id'])
                ->active()
                ->first();
        }
        // 2. Archive ALL active records for this CR
        ChangeRequestStatus::find($currentStatus->id)
            ->update(['active' => self::COMPLETED_STATUS]);

        // 3. Create the new record for the next status
        $toStatusId = $workflow->workflowstatus->first()->to_status_id ?? null;
        if (!$toStatusId) {
            return;
        }

        $payload = $this->buildStatusData(
            $changeRequest->id,
            $statusData['old_status_id'],
            (int) $toStatusId,
            $previousGroupId, // group_id null for main status row
            (int) $previousGroupId, // reference_group_id = inherited from previous_group_id
            (int) $oldCurrentGroupId, // previous_group_id = the one that just moved it
            (int) $previousGroupId, // current_group_id = inherited from previous_group_id
            $this->getUserId($changeRequest, request()),
            self::ACTIVE_STATUS // '1'
        );

        $statusRepository = new ChangeRequestStatusRepository();
        $statusRepository->create($payload);

    }


    /**
     * Determine user ID for the status update
     */
    private function getUserId(Change_request $changeRequest, $request): int
    {
        return Auth::id();

    }

    private function getWorkflow(array $statusData): ?NewWorkFlow
    {
        $workflowId = $statusData['new_workflow_id'] ?: $statusData['new_status_id'];

        return NewWorkFlow::find($workflowId);
    }

}
