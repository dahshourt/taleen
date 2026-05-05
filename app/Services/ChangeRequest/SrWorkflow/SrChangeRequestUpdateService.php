<?php

namespace App\Services\ChangeRequest\SrWorkflow;

use App\Http\Repository\ChangeRequest\ChangeRequestStatusRepository;
use App\Http\Repository\Logs\LogRepository;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\ChangeRequestCustomField;
use App\Models\CustomField;
use App\Models\NewWorkFlow;
use App\Models\SrTechnicalCr;
use App\Models\SrTechnicalCrTeam;
use App\Models\Status;
use App\Models\SrBusinessApprovalCr;
use App\Models\SrBusinessApprovalCrUser;
use App\Models\User;
use App\Traits\ChangeRequest\ChangeRequestConstants;
use Auth;
use Illuminate\Support\Arr;
use App\Events\ChangeRequestUserAssignment;
use App\Services\ChangeRequest\ChangeRequestEstimationService;
use App\Services\ChangeRequest\ChangeRequestStatusService;
use App\Services\ChangeRequest\CrDependencyService;
use Illuminate\Support\Facades\Log;

/**
 * Dedicated update service for SR Workflow (type 15) Change Requests.
 *
 * Extends the standard update flow but replaces the technical team
 * validation step with the SR-specific version that checks
 * view_sr_technical_team_flag and reads sr_technical_teams from the form.
 */
class SrChangeRequestUpdateService
{
    use ChangeRequestConstants;

    private const ACTIVE_STATUS = '1';
    private const INACTIVE_STATUS = '0';
    private const COMPLETED_STATUS = '2';

    public static array $ACTIVE_STATUS_ARRAY = [self::ACTIVE_STATUS, 1];
    public static array $INACTIVE_STATUS_ARRAY = [self::INACTIVE_STATUS, 0];
    public static array $COMPLETED_STATUS_ARRAY = [self::COMPLETED_STATUS, 2];

    protected $logRepository;
    protected $statusRepository;
    protected $estimationService;
    protected $srValidationService;
    protected $statusService;

    private $changeRequest_old;

    public function __construct()
    {
        $this->logRepository = new LogRepository();
        $this->statusRepository = new ChangeRequestStatusRepository();
        $this->estimationService = new ChangeRequestEstimationService();
        $this->srValidationService = new SrChangeRequestValidationService();
        $this->statusService = new ChangeRequestStatusService();
    }

    /**
     * Update an SR Change Request.
     *
     * This mirrors ChangeRequestUpdateService::update() but:
     * 1) Uses SrChangeRequestValidationService for technical team validation
     * 2) Maps sr_technical_teams form input to technical_teams for downstream processing
     */
    public function update($id, $request): bool
    {

        $this->changeRequest_old = Change_request::with('kpis', 'changeRequestCustomFields', 'dependencies')->find($id);

        // 0) Link KPI if selected
        if (isset($request['kpi']) && $request['kpi']) {
            $kpiRepo = new \App\Http\Repository\KPIs\KPIRepository();
            $kpiResult = $kpiRepo->attachKpiToChangeRequest($request['kpi'], $this->changeRequest_old->cr_no);
            if (isset($kpiResult['success']) && !$kpiResult['success']) {
                return true;
            }
        }

        // Map sr_technical_teams to technical_teams so downstream services work correctly
        $this->mapSrTechnicalTeams($request);

        // 2) SR-specific technical team validation
        if ($this->handleSrTechnicalTeamValidation($id, $request)) {
            try {
                $statusData = $this->statusService->extractStatusData($request);
                $this->logRepository->logCreate($id, $request, $this->changeRequest_old, 'update');
            } catch (\Throwable $e) {
                Log::error('Error in SR technical team validation logging', [
                    'cr_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }

            return true;
        }

        // 3) Assignments
        $this->handleUserAssignments($id, $request);

        // 4) SR Technical teams bootstrap (parallel streams)
        $this->handleSrTechnicalTeams($id, $request);

        // 5) Estimations
        $this->handleEstimations($id, $request);

        // 6) Update CR data (custom fields + main cols)
        $this->updateCRData($id, $request);

        // 7) Update assignment on current CR status row
        $this->updateStatusAssignments($id, $request);

        // 8) CR-level status move (main workflow)
        if (isset($request->new_status_id)) {
            $this->handleSrBusinessApproval($id, $request);
            $this->statusService->updateChangeRequestStatus($id, $request);
        }
        if (!isset($request->new_status_id)) {
            event(new ChangeRequestUserAssignment($this->changeRequest_old, $request));
        }

        // 9) Audit
        $this->logRepository->logCreate($id, $request, $this->changeRequest_old, 'update');

        return true;
    }

    /**
     * Map sr_technical_teams from the form to technical_teams
     * so all downstream services (TechnicalCr, TechnicalCrTeam, etc.) work correctly.
     */
    private function mapSrTechnicalTeams($request): void
    {
        if (isset($request->sr_technical_teams) && !empty($request->sr_technical_teams)) {
            $request->merge(['technical_teams' => $request->sr_technical_teams]);
        }
    }

    /**
     * Delegate to SR-specific technical team validation.
     */
    protected function handleSrTechnicalTeamValidation($id, $request): bool
    {

        return $this->srValidationService->handleSrTechnicalTeamValidation($id, $request);
    }

    /* ======================================================================
     |                          ASSIGNMENTS
     * ====================================================================== */
    protected function handleUserAssignments($id, $request): void
    {
        $user = $request['assign_to'] ? User::find($request['assign_to']) : Auth::user();
    }

    /* ======================================================================
     |                    SR TECHNICAL TEAMS (BOOTSTRAP)
     * ====================================================================== */
    protected function handleSrTechnicalTeams($id, $request): void
    {
        $newStatusId = $request->new_status_id ?? null;
        $workflow = $newStatusId ? NewWorkFlow::find($newStatusId) : null;

        if (empty($request->technical_teams)) {
            return;
        }

        // Use SrTechnicalCr instead of TechnicalCr
        $record = SrTechnicalCr::create([
            'cr_id' => $id,
            'status' => '0',
        ]);

        foreach ($request->technical_teams as $groupId) {
            // Use SrTechnicalCrTeam instead of TechnicalCrTeam
            SrTechnicalCrTeam::create([
                'group_id' => $groupId,
                'technical_cr_id' => $record->id,
                'current_status_id' => $workflow && isset($workflow->workflowstatus[0])
                    ? $workflow->workflowstatus[0]->to_status_id
                    : null,
                'status' => '0',
            ]);
        }

        // Auto-mirror CR status to tech stream(s)
        if (isset($request->new_status_id)) {
            $new_status_id = $workflow && isset($workflow->workflowstatus[0])
                ? $workflow->workflowstatus[0]->to_status_id : null;

            $this->mirrorSrCrStatusToTechStreams($id, (int) $new_status_id, $request->tech_note ?? null, 'all');
        }
    }

    /**
     * Mirror SR CR status to technical streams.
     * SR version of mirrorCrStatusToTechStreams.
     */
    public function mirrorSrCrStatusToTechStreams(int $crId, int $toStatusId, ?string $note = null, string $scope = 'actor'): void
    {
        if ($scope === 'all') {
            $teams = SrTechnicalCrTeam::query()
                ->whereHas('srTechnicalCr', fn($q) => $q->where('cr_id', $crId))
                ->get();
        } else { // actor
            $actorGroupId = session('default_group') ?: auth()->user()->default_group;
            if (!$actorGroupId) {
                return;
            }

            $teams = SrTechnicalCrTeam::query()
                ->whereHas('srTechnicalCr', fn($q) => $q->where('cr_id', $crId))
                ->where('group_id', $actorGroupId)
                ->get();
        }
        foreach ($teams as $team) {
            $this->advanceSrTeamStream($team->id, $toStatusId, $note ?? 'auto: mirrored from CR status');
        }
    }

    /**
     * Advance SR technical team stream status.
     * SR version of advanceTeamStream.
     */
    protected function advanceSrTeamStream(int $technicalCrTeamId, int $toStatusId, ?string $note = null): void
    {
        $team = SrTechnicalCrTeam::find($technicalCrTeamId);
        if (!$team) {
            return;
        }
        $oldStatusId = request()->old_status_id ?? 0;
        $fromStatusId = (int) ($team->current_status_id ?? $oldStatusId);


        $team->update(['current_status_id' => $toStatusId]);
    }

    /* ======================================================================
     |                          ESTIMATION
     * ====================================================================== */
    protected function handleEstimations($id, $request): void
    {
        $changeRequest = Change_request::find($id);
        $user = $request['assign_to'] ? User::find($request['assign_to']) : Auth::user();

        if ($this->needsEstimationCalculation($request)) {
            $data = $this->estimationService->calculateEstimation($id, $changeRequest, $request, $user);
            $request->merge($data);
        }
    }

    protected function needsEstimationCalculation($request): bool
    {
        return (isset($request['CR_duration']) && $request['CR_duration'] != '') ||
            (isset($request['dev_estimation']) && $request['dev_estimation'] != '') ||
            (isset($request['design_estimation']) && $request['design_estimation'] != '') ||
            (isset($request['testing_estimation']) && $request['testing_estimation'] != '');
    }

    /* ======================================================================
     |                          CORE DATA UPDATE
     * ====================================================================== */
    public function updateCRData($id, $request)
    {
        $arr = Arr::only($request->all(), $this->getRequiredFields());
        $fileFields = ['technical_attachments', 'business_attachments', 'cap_users', 'technical_teams', 'sr_technical_teams'];
        $data = Arr::except($request->all(), array_merge(['_method'], $fileFields));

        $this->handleCustomFieldUpdates($id, $data);

        return Change_request::where('id', $id)->update($arr);
    }

    protected function handleCustomFieldUpdates($id, $data): void
    {
        // Handle custom_fields array if present
        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            foreach ($data['custom_fields'] as $key => $value) {
                $this->updateCustomField($id, $key, $value);
            }
        }

        // Handle direct fields (legacy support)
        foreach ($data as $key => $value) {
            if ($key === 'testable') {
                $testable = (string) $value === '1' ? 1 : 0;
                $this->updateCustomField($id, $key, $testable);
            } elseif (!in_array($key, ['_token', 'testable', 'custom_fields', 'cr'])) {
                $this->updateCustomField($id, $key, $value);
            }
        }
    }

    protected function updateCustomField($crId, $fieldName, $fieldValue): void
    {
        if ($fieldValue === null) {
            return;
        }

        $customField = CustomField::where('name', $fieldName)->first();

        if ($customField) {
            $fieldValue = is_array($fieldValue) ? json_encode($fieldValue) : $fieldValue;

            $changeRequestCustomField = [
                'cr_id' => $crId,
                'custom_field_id' => $customField->id,
                'custom_field_name' => $fieldName,
                'custom_field_value' => $fieldValue,
                'user_id' => auth()->id(),
            ];

            $this->insertOrUpdateChangeRequestCustomField($changeRequestCustomField);
        }
    }

    protected function insertOrUpdateChangeRequestCustomField(array $data): void
    {
        if (in_array($data['custom_field_name'], ['technical_feedback', 'business_feedback'])) {
            ChangeRequestCustomField::create([
                'cr_id' => $data['cr_id'],
                'custom_field_id' => $data['custom_field_id'],
                'custom_field_name' => $data['custom_field_name'],
                'custom_field_value' => $data['custom_field_value'],
                'user_id' => $data['user_id'],
            ]);
        } else {
            ChangeRequestCustomField::updateOrCreate(
                [
                    'cr_id' => $data['cr_id'],
                    'custom_field_id' => $data['custom_field_id'],
                    'custom_field_name' => $data['custom_field_name'],
                ],
                [
                    'custom_field_value' => $data['custom_field_value'],
                    'user_id' => $data['user_id'],
                ]
            );
        }
    }

    protected function updateStatusAssignments($id, $request): void
    {
        $oldStatusId = $request->old_status_id ?? null;

        if (isset($request->assignment_user_id) && $oldStatusId) {
            Change_request_statuse::where('cr_id', $id)
                ->where('new_status_id', $oldStatusId)
                ->active()
                ->update(['assignment_user_id' => $request->assignment_user_id]);
        }

        $memberFields = ['cr_member', 'rtm_member', 'assignment_user_id', 'tester_id', 'developer_id', 'designer_id'];
        foreach ($memberFields as $field) {
            if (isset($request->$field) && $oldStatusId) {
                Change_request_statuse::where('cr_id', $id)
                    ->where('new_status_id', $oldStatusId)
                    ->active()
                    ->update(['assignment_user_id' => $request->$field]);
            }
        }
    }

    /**
     * Handle SR Business Approval tracking records.
     */
    protected function handleSrBusinessApproval(int $crId, $request): void
    {
        $newStatusId = $request->new_status_id;
        $oldStatusId = $request->old_status_id;
        if (!$newStatusId || !$oldStatusId) {
            return;
        }
        $toStatusId = null;
        if ($newStatusId) {
            $workflow = NewWorkFlow::with('workflowstatus')->find($newStatusId);
            $toStatusId = $workflow && $workflow->workflowstatus->isNotEmpty()
                ? $workflow->workflowstatus[0]->to_status_id
                : null;
        }
        $newStatus = Status::find($toStatusId);
        $oldStatus = Status::find($oldStatusId);

        if (!$newStatus || !$oldStatus) {
            return;
        }

        $newStatusName = $newStatus->status_name;
        $oldStatusName = $oldStatus->status_name;

        $checkNeededApprovalStatus = config('change_request.sr_workflow.check_needed_approval');
        $srBusinessApprovalStatus = config('change_request.sr_workflow.sr_business_approval');
        $techApprovalStatus = config('change_request.sr_workflow.technical_and_business_approval');

        // Detect transition from "Check Needed Approval" to "SR Business Approval" or "Technical and Business Approval"
        if (
            $oldStatusName === $checkNeededApprovalStatus &&
            in_array($newStatusName, [$srBusinessApprovalStatus, $techApprovalStatus])
        ) {

            $cr = Change_request::with('changeRequestCustomFields')->find($crId);
            if (!$cr)
                return;

            $userIds = [];

            // 1) Resolve "approved_by" (comma-separated names)
            $approvedByField = $cr->changeRequestCustomFields->where('custom_field_name', 'approved_by')->first();
            if ($approvedByField && !empty($approvedByField->custom_field_value)) {
                $names = explode(',', $approvedByField->custom_field_value);
                $names = array_map('trim', $names);
                $users = User::whereIn('name', $names)->orWhereIn('user_name', $names)->pluck('id')->toArray();
                $userIds = array_merge($userIds, $users);
            }
            // 2) Resolve "sr_te_validators" (JSON array of IDs) if status is "Technical and Business Approval"
            if ($newStatusName === $techApprovalStatus) {
                $teValidatorsField = $cr->changeRequestCustomFields->where('custom_field_name', 'sr_te_validators')->first();

                if ($teValidatorsField && !empty($teValidatorsField->custom_field_value)) {
                    $decoded = json_decode($teValidatorsField->custom_field_value, true);
                    if (is_array($decoded)) {
                        $userIds = array_merge($userIds, $decoded);
                    } else {
                        $userIds[] = $decoded;
                    }
                } else {
                    $userIds[] = $request->sr_te_validators;
                }
            }

            $userIds = array_unique(array_filter($userIds));

            if (!empty($userIds)) {
                $record = SrBusinessApprovalCr::create([
                    'cr_id' => $crId,
                    'status' => SrBusinessApprovalCr::PENDING,
                ]);

                foreach ($userIds as $userId) {
                    SrBusinessApprovalCrUser::create([
                        'user_id' => $userId,
                        'sr_business_approval_cr_id' => $record->id,
                        'status' => SrBusinessApprovalCrUser::PENDING,
                    ]);
                }
            }

        }
    }

    /* ======================================================================
     |              SR BUSINESS APPROVAL (Requester + TE Validators)
     * ====================================================================== */

    public function approveSrBusinessApproval(int $crId, int $userId): array
    {
        $approvalCr = SrBusinessApprovalCr::where('cr_id', $crId)
            ->where('status', SrBusinessApprovalCr::PENDING)
            ->first();

        if (!$approvalCr) {
            return ['success' => false, 'message' => 'No pending business approval found for this SR.'];
        }

        $userApproval = $approvalCr->approvalUsers()
            ->where('user_id', $userId)
            ->where('status', SrBusinessApprovalCrUser::PENDING)
            ->first();

        if (!$userApproval) {
            return ['success' => false, 'message' => 'You have already acted on this business approval.'];
        }

        $userApproval->update(['status' => SrBusinessApprovalCrUser::APPROVED]);

        $user = User::find($userId);
        $userName = $user ? $user->user_name : 'Unknown';

        $countAllUsers = $approvalCr->approvalUsers()->count();
        $countApprovedUsers = $approvalCr->approvalUsers()->where('status', SrBusinessApprovalCrUser::APPROVED)->count();

        $this->logRepository->create([
            'cr_id' => $crId,
            'user_id' => $userId,
            'log_text' => "SR Business Approval approved by '{$userName}'. Waiting for other approvers ({$countApprovedUsers}/{$countAllUsers})",
        ]);

        if ($countAllUsers == $countApprovedUsers) {
            $approvalCr->update(['status' => SrBusinessApprovalCr::APPROVED]);

            $this->logRepository->create([
                'cr_id' => $crId,
                'user_id' => $userId,
                'log_text' => "SR Business Approval fully approved. Last approved by '{$userName}'",
            ]);

            $this->moveSrCrAfterBusinessApproval($crId, $userId, 'Approve');

            return ['success' => true, 'fully_approved' => true, 'message' => 'SR fully approved.'];
        }

        return ['success' => true, 'fully_approved' => false, 'message' => 'Approval recorded. Waiting for other approvers.'];
    }

    public function rejectSrBusinessApproval(int $crId, int $userId): array
    {
        $approvalCr = SrBusinessApprovalCr::where('cr_id', $crId)
            ->where('status', SrBusinessApprovalCr::PENDING)
            ->first();

        if (!$approvalCr) {
            return ['success' => false, 'message' => 'No pending business approval found for this SR.'];
        }

        $userApproval = $approvalCr->approvalUsers()
            ->where('user_id', $userId)
            ->where('status', SrBusinessApprovalCrUser::PENDING)
            ->first();

        if (!$userApproval) {
            return ['success' => false, 'message' => 'You have already acted on this business approval.'];
        }

        $userApproval->update(['status' => SrBusinessApprovalCrUser::REJECTED]);
        $approvalCr->update(['status' => SrBusinessApprovalCr::REJECTED]);

        $user = User::find($userId);
        $userName = $user ? $user->user_name : 'Unknown';

        $this->logRepository->create([
            'cr_id' => $crId,
            'user_id' => $userId,
            'log_text' => "SR Business Approval rejected by '{$userName}'",
        ]);

        $this->moveSrCrAfterBusinessApproval($crId, $userId, 'Reject');

        return ['success' => true, 'message' => 'SR business approval rejected.'];
    }

    protected function moveSrCrAfterBusinessApproval(int $crId, int $userId, string $action): void
    {
        $cr = Change_request::find($crId);
        if (!$cr) {
            return;
        }

        $currentStatusId = $cr->getCurrentStatus()?->new_status_id;
        if (!$currentStatusId) {
            return;
        }

        $targetStatusName = $action === 'Approve' ? 'Pending Assigned to Tech team' : 'Request Rejected';
        $targetStatus = Status::where('status_name', $targetStatusName)->where('active', '1')->first();

        if (!$targetStatus) {
            return;
        }

        // Find the workflow transition from current status to the target status
        $workflow = NewWorkFlow::where('from_status_id', $currentStatusId)
            ->where('type_id', $cr->workflow_type_id)
            ->whereHas('workflowstatus', function ($query) use ($targetStatus) {
                $query->where('to_status_id', $targetStatus->id);
            })
            ->active()
            ->first();

        if (!$workflow) {
            return;
        }

        $updateRequest = new \Illuminate\Http\Request([
            'old_status_id' => $currentStatusId,
            'new_status_id' => $workflow->id,
            'user_id' => $userId,
        ]);

        $this->update($crId, $updateRequest);
    }

}
