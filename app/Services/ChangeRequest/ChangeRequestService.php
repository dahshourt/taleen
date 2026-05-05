<?php

namespace App\Services\ChangeRequest;
use App\Factories\Applications\ApplicationFactory;
use App\Factories\ChangeRequest\AttachmetsCRSFactory;
use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\CustomField\CustomFieldGroupTypeFactory;
use App\Factories\Defect\DefectFactory;
use App\Factories\Groups\GroupFactory;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Factories\Users\UserFactory;
use App\Factories\Workflow\Workflow_type_factory;
use App\Http\Controllers\Mail\MailController;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Repository\RejectionReasons\RejectionReasonsRepository;
use App\Models\ApplicationImpact;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\ChangeRequestTechnicalTeam;
use App\Services\ChangeRequest\ChangeRequestStatusService;
use App\Services\StatusConfigService;
use App\Models\Group;
use App\Models\ManDaysLog;
use App\Models\NewWorkFlow;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Throwable;

class ChangeRequestService
{
    private $changerequest;
    private $changerequeststatus;
    private $workflow;
    private $workflow_type;
    private $attachments;
    private $custom_field_group_type;
    private $applications;
    private $defects;
    private $changeRequestStatusService;

    public function __construct(
        DefectFactory $defect,
        ChangeRequestFactory $changerequest,
        ChangeRequestStatusFactory $changerequeststatus,
        NewWorkFlowFactory $workflow,
        AttachmetsCRSFactory $attachments,
        Workflow_type_factory $workflow_type,
        CustomFieldGroupTypeFactory $custom_field_group_type,
        ApplicationFactory $applications,
        ChangeRequestStatusService $changeRequestStatusService
    ) {
        $this->changerequest = $changerequest::index();
        $this->defects = $defect::index();
        $this->changerequeststatus = $changerequeststatus::index();
        $this->workflow = $workflow::index();
        $this->workflow_type = $workflow_type::index();
        $this->attachments = $attachments::index();
        $this->custom_field_group_type = $custom_field_group_type::index();
        $this->applications = $applications::index();
        $this->changeRequestStatusService = $changeRequestStatusService;
    }

    public function createChangeRequest(array $data)
    {
        return $this->changerequest->create($data);
    }

    public function updateChangeRequest(int $id, Request $request)
    {
        $cr = Change_request::find($id);
        if ($cr && $cr->workflow_type_id == 15) {
            $groupId = null;
            if ($request->start_date_mds) {
                if ($request->reference_status) {
                    $refStatus = Change_request_statuse::find($request->reference_status);
                    $groupId = $refStatus ? $refStatus->previous_group_id : null;
                }

                $mdsLog = ManDaysLog::where('cr_id', $id)
                    ->where('group_id', $groupId)
                    ->first();
                $manDays = $mdsLog ? $mdsLog->man_day : null;
                $startDate = $request->start_date_mds;
                $endDate = null;
                if ($startDate && $manDays) {
                    $endDate = $this->calculateEndDateMds($startDate, $manDays);
                }
                if ($mdsLog) {
                    $mdsLog->update([
                        'start_date' => $request->start_date_mds,
                        'end_date' => $endDate,
                    ]);
                }
            }
        }
        if ($request->man_days && !empty($request->man_days)) {
            $startDate = $request->start_date_mds;
            $manDays = $request->man_days;
            $endDate = null;

            if ($startDate && $manDays) {
                $endDate = $this->calculateEndDateMds($startDate, $manDays);
            }

            $mds = ManDaysLog::where('cr_id', $id)->where('group_id', Session::get('current_group'))->first();
            if ($mds) {
                $mds->update([
                    'group_id' => Session::get('current_group'),
                    'user_id' => auth()->user()->id,
                    'cr_id' => $id,
                    'man_day' => $request->man_days,
                    'start_date' => $request->start_date_mds,
                    'end_date' => $endDate,
                ]);
            } else {
                ManDaysLog::create([
                    'group_id' => Session::get('current_group'),
                    'user_id' => auth()->user()->id,
                    'cr_id' => $id,
                    'man_day' => $request->man_days,
                    'start_date' => $request->start_date_mds,
                    'end_date' => $endDate,
                ]);
            }
        }

        if ($request->cap_users) {
            $cap_users = array_unique($request->cap_users);

            if ($request->cr) {
                $requesterId = $request->cr->requester_id;
                $requester = User::find($requesterId);
                $requesterGroupId = $requester->default_group;
                $crTeamGroup = Group::where('title', config('constants.group_names.cr_team'))->first();

                if ($requesterGroupId == $crTeamGroup->id) {
                    if (in_array($requesterId, $cap_users)) {
                        if (count($cap_users) < 2) {
                            throw new Exception('You cannot be the only CAB user. Please select at least one additional CAB user.');
                        }
                    }
                }
            }
        }

        $this->assignTechnicalTeams($request, $id);

        $cr_id = $this->changerequest->update($id, $request);

        if ($cr_id === false) {
            throw new Exception('Failed to update change request');
        }

        $this->handleCapUsersNotification($request, $id);

        return $cr_id;
    }

    private function calculateEndDateMds($startDate, $manDays)
    {
        $currentDate = \Carbon\Carbon::parse($startDate);
        $daysRemaining = ceil((float) $manDays);

        // adjust if starting on a weekend (optional, but good practice if start_date can be weekend)
        // If start date is Friday/Saturday, move to Sunday?
        // User requirement: "exclude friday and starday".
        // Use basic loop logic.

        $addedDays = 0;
        while ($daysRemaining > 0) {
            // Check if current day is Friday or Saturday
            if ($currentDate->isFriday() || $currentDate->isSaturday()) {
                $currentDate->addDay();
                continue;
            }

            // It's a working day
            $daysRemaining--;
            if ($daysRemaining > 0) {
                $currentDate->addDay();
            }
        }

        return $currentDate->toDateString();
    }

    public function updateManDaysDate(int $id, $startDate)
    {
        $log = ManDaysLog::find($id);
        if (!$log) {
            throw new Exception("Man Days Log not found");
        }

        // Capture old start date before update
        $oldStartDate = $log->start_date?->format('Y-m-d');

        $endDate = $this->calculateEndDateMds($startDate, $log->man_day);

        $log->update([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        // Fire event for MDS start date change notification
        $changeRequest = Change_request::find($log->cr_id);
        if ($changeRequest) {
            event(new \App\Events\MdsStartDateUpdated(
                $log,
                $changeRequest,
                $oldStartDate,
                $startDate,
                $log->group_id
            ));
        }

        return $log;
    }

    private function assignTechnicalTeams(Request $request, int $id): void
    {
        if (!isset($request->technical_teams) || empty($request->technical_teams)) {
            return;
        }

        foreach ($request->technical_teams as $teamId) {
            DB::table('change_request_technical_team')->insert([
                'cr_id' => $id,
                'technical_team_id' => $teamId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function handleCapUsersNotification(Request $request, int $id): void
    {
        if (empty($request->cap_users)) {
            return;
        }

        $emails = [];
        foreach ($request->cap_users as $userId) {
            $user = User::find($userId);
            if ($user) {
                $emails[] = $user->email;
            }
        }
        $cr = Change_request::find($id);

        if (!empty($emails)) {
            $mail = new MailController();
            $mail->send_mail_to_cap_users($emails, $id, $cr->cr_no);
        }
    }

    public function prepareEditData($cr, int $id): array
    {
        // Get users by workflow type
        $developer_users = $this->getDeveloperUsers($cr);
        $technical_groups = $this->getTechnicalGroups($cr);
        $sa_users = UserFactory::index()->get_user_by_department_id(6);
        $testing_users = UserFactory::index()->get_user_by_department_id(3);
        $cap_users = UserFactory::index()->get_users_cap($cr->application_id);
        $rtm_members = UserFactory::index()->get_user_by_group_id(23);
        $sr_te_validators = UserFactory::index()->get_users_te_validators($cr->application_id);

        // Get technical teams and related data
        $technical_teams = Group::where('technical_team', '1')->get();
        $technical_team_disabled = ChangeRequestTechnicalTeam::where('cr_id', $id)->get();
        $sr_technical_teams = Group::where('sr_technical_team', '1')->get();

        $sr_services_field = $cr->change_request_custom_fields
            ->where('custom_field_name', 'sr_services')
            ->first();

        if ($sr_services_field && !empty($sr_services_field->custom_field_value)) {
            $decoded = json_decode($sr_services_field->custom_field_value, true);
            $serviceIds = is_array($decoded) ? $decoded : [$sr_services_field->custom_field_value];

            $teamIds = DB::table('sr_service_technical_team')
                ->whereIn('sr_service_id', $serviceIds)
                ->pluck('group_id')
                ->toArray();

            $sr_technical_teams = Group::whereIn('id', $teamIds)
                ->where('sr_technical_team', '1')
                ->get();

            $preconfigured_teams_count = count($teamIds);
        } else {
            $preconfigured_teams_count = 0;
        }

        // Get custom fields and other data
        $workflow_type_id = $cr->workflow_type_id;
        $status_id = $cr->getCurrentStatus()->status->id;
        $status_name = $cr->getCurrentStatus()->status->name;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowTypeAndStatus(
            $workflow_type_id,
            2, // FORM_TYPE_EDIT
            $status_id
        );

        $biMappedFieldsNames = [];
        if (in_array($workflow_type_id, [16, 17])) {
            // 1. Get all BI-mapped custom field names
            $allMappedFieldIds = DB::table('bi_request_type_fields')->distinct()->pluck('custom_field_id');
            $allMappedFieldNames = DB::table('custom_fields')->whereIn('id', $allMappedFieldIds)->pluck('name')->toArray();

            // 2. Get the 'type_of_request' value for this CR
            $typeOfRequestField = $cr->change_request_custom_fields
                ->where('custom_field_name', 'type_of_request')
                ->first();

            if ($typeOfRequestField && !empty($typeOfRequestField->custom_field_value)) {
                $requestTypeId = $typeOfRequestField->custom_field_value;

                // 3. Get fields for THIS specific request type
                $shownFieldIds = DB::table('bi_request_type_fields')
                    ->where('request_type_id', $requestTypeId)
                    ->pluck('custom_field_id');

                $shownFieldNames = DB::table('custom_fields')->whereIn('id', $shownFieldIds)->pluck('name')->toArray();

                // 4. Fields to HIDE are (all - shown)
                $biMappedFieldsNames = array_diff($allMappedFieldNames, $shownFieldNames);
            } else {
                // If no request type is selected, hide ALL BI-mapped fields
                $biMappedFieldsNames = $allMappedFieldNames;
            }
        }

        $logs_ers = $cr->logs->load('user:id,user_name,default_group', 'user.defualt_group:id,title');
        $all_defects = $this->defects->all_defects($id);
        $ApplicationImpact = ApplicationImpact::where('application_id', $cr->application_id)
            ->select('impacts_id')
            ->get();

        // Get technical team data
        $selected_technical_teams = $this->getSelectedTechnicalTeams($cr);
        $selected_sr_technical_teams = $this->getSelectedSrTechnicalTeams($cr);
        $selected_sr_technical_teams_count = count($selected_sr_technical_teams);

        $reminder_promo_tech_teams = $this->getReminderPromoTechTeams($cr);
        $reminder_promo_tech_teams_text = implode(',', $reminder_promo_tech_teams);
        // Get assignment users
        $view_by_groups = $cr->getCurrentStatus()->status->group_statuses
            ->where('type', '2')
            ->pluck('group_id')
            ->toArray();
        $assignment_users = UserFactory::index()->GetAssignmentUsersByViewGroups($view_by_groups);

        $man_day = $cr->change_request_custom_fields
            ->where('custom_field_name', 'man_days')
            ->values()
            ->toArray();
        $reject = new RejectionReasonsRepository();
        $rejects = $reject->workflows($workflow_type_id);

        $man_days = ManDaysLog::where('cr_id', $id)->with('user')->get();
        $relevantField = $cr->change_request_custom_fields
            ->where('custom_field_name', 'relevant')
            ->first();

        // Step 2: decode the JSON values into selected CR IDs
        $selectedRelevant = [];
        if ($relevantField && !empty($relevantField->custom_field_value)) {
            $decoded = json_decode($relevantField->custom_field_value, true);
            if (is_array($decoded)) {
                $selectedRelevant = array_map('intval', $decoded);
            }
        }

        // Step 3: load these CRs with status
        $relevantCrsData = Change_request::whereIn('id', $selectedRelevant)
            ->with('CurrentRequestStatuses')
            ->get(['id', 'cr_no', 'title']);

        // Step 4: check if any relevant CR is not in Pending Production Deployment
        $pendingProductionId = StatusConfigService::getStatusId('pending_production_deployment');
        $relevantNotPending = $relevantCrsData->filter(function ($item) use ($pendingProductionId) {
            return $item->CurrentRequestStatuses_last?->new_status_id != $pendingProductionId;
        })->count();

        // Get CRs that can be depended upon
        $dependableCrs = Change_request::getDependableCrs($cr->id);

        return compact(
            'rejects',
            'selected_technical_teams',
            'man_day',
            'technical_team_disabled',
            'status_name',
            'ApplicationImpact',
            'cap_users',
            'CustomFields',
            'cr',
            'workflow_type_id',
            'logs_ers',
            'developer_users',
            'sa_users',
            'testing_users',
            'technical_teams',
            'all_defects',
            'reminder_promo_tech_teams',
            'rtm_members',
            'assignment_users',
            'reminder_promo_tech_teams_text',
            'technical_groups',
            'man_days',
            'relevantCrsData',
            'relevantNotPending',
            'pendingProductionId',
            'sr_technical_teams',
            'sr_te_validators',
            'dependableCrs',
            'preconfigured_teams_count',
            'selected_sr_technical_teams',
            'selected_sr_technical_teams_count',
            'biMappedFieldsNames'
        );
    }

    private function getDeveloperUsers($cr)
    {
        if ($cr->workflow_type_id == 13) {
            $parentCR = DB::table('parents_crs')
                ->where('id', $cr->change_request_custom_fields
                    ->where('custom_field_name', 'parent_id')
                    ->values()
                    ->toArray()[0]['custom_field_value'] ?? null)
                ->value('application_name');

            $res = ApplicationFactory::index()->get_app_id_by_name($parentCR);

            return $res
                ? UserFactory::index()->get_user_by_group($res->id)
                : UserFactory::index()->get_user_by_group($cr->application_id);
        }
        $tech_group = $cr->change_request_custom_fields->where('custom_field_name', 'tech_group_id')->first();
        $tech_group_id = $tech_group ? $tech_group->custom_field_value : null;
        if ($tech_group_id) {
            return UserFactory::index()->get_user_by_group_id($tech_group_id);
        }

        return UserFactory::index()->get_user_by_group($cr->application_id);
    }

    private function getTechnicalGroups($cr)
    {
        return GroupFactory::index()->get_tech_groups_by_application($cr->application_id);
    }

    private function getSelectedTechnicalTeams($cr): array
    {
        try {
            return $cr->technical_Cr_first->technical_cr_team->pluck('group')->toArray();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function getSelectedSrTechnicalTeams($cr): array
    {
        try {
            return $cr->srTechnicalCrFirst->technical_cr_team->pluck('group')->toArray();
        } catch (Throwable $e) {
            return [];
        }
    }

    private function getReminderPromoTechTeams($cr): array
    {
        return $cr->technical_Cr
            ? $cr->technical_Cr->technical_cr_team
                ->where('status', '0')
                ->pluck('group')
                ->pluck('title')
                ->toArray()
            : [];
    }

    public function generateSecurityToken($cr): string
    {
        return md5($cr->id . $cr->created_at . env('APP_KEY'));
    }

    public function processDivisionManagerAction($cr, string $action, int $current_status)
    {
        $workflow_type_id = $cr->workflow_type_id;

        // Determine workflow ID based on action and workflow type
        $workflowIdForAction = $this->getWorkflowIdForAction($workflow_type_id, $action);

        if (!$workflowIdForAction) {
            throw new Exception('Unsupported workflow type.');
        }

        $repository = new ChangeRequestRepository();
        $updateRequest = new Request([
            'old_status_id' => $current_status,
            'new_status_id' => $workflowIdForAction,
        ]);

        $repository->UpateChangeRequestStatus($cr->id, $updateRequest);

        return $workflowIdForAction;
    }

    private function getWorkflowIdForAction(int $workflow_type_id, string $action): ?int
    {
        $workflowMap = [
            3 => [
                'approve' => $this->GetDivisionManagerActionId(3, 'Business Approval', 'Business Validation'),
                'reject' => $this->GetDivisionManagerActionId(3, 'Business Approval', 'Reject'),
            ],
            5 => [
                'approve' => $this->GetDivisionManagerActionId(5, 'Business Approval', 'CR Analysis'),
                'reject' => $this->GetDivisionManagerActionId(5, 'Business Approval', 'Reject'),
            ],
            37 => [
                'approve' => $this->GetDivisionManagerActionId(37, 'Business Approval kam', 'Business Validation kam'),
                'reject' => $this->GetDivisionManagerActionId(37, 'Business Approval kam', 'Reject kam'),
            ],
        ];

        return $workflowMap[$workflow_type_id][$action] ?? null;
    }

    private function GetDivisionManagerActionId(int $workflow_type_id, string $from_action, string $to_action): ?int
    {
        return NewWorkflow::query()
            ->select('new_workflow.id')
            ->join('statuses as s1', function ($join) use ($from_action) {
                $join->on('s1.id', '=', 'new_workflow.from_status_id')
                    ->where('s1.status_name', 'like', '%' . $from_action . '%');
            })
            ->join('new_workflow_statuses as nws', 'nws.new_workflow_id', '=', 'new_workflow.id')
            ->join('statuses as s2', function ($join) use ($to_action) {
                $join->on('s2.id', '=', 'nws.to_status_id')
                    ->where('s2.status_name', 'like', '%' . $to_action . '%');
            })
            ->where('new_workflow.type_id', $workflow_type_id)
            ->orderBy('new_workflow.id', 'desc')
            ->value('new_workflow.id');
    }

    public function approvedActive(Request $request)
    {
        $id = $request->get('id');
        $this->changerequest->UpateChangeRequestStatus($id, $request);
    }

    public function approvedContinue(Request $request)
    {
        $cr_id = $request->get('crId');
        $action = $request->get('action');

        if (!$cr_id || !$action) {
            throw new Exception('Invalid request. Missing parameters.');
        }

        $cr = Change_request::find($cr_id);
        if (!$cr) {
            throw new Exception('Change Request not found.');
        }

        $cr_repo = app(ChangeRequestRepository::class);

        if ($action === 'approve') {
            Change_request_statuse::where('cr_id', $cr->id)
                ->where('active', '3')
                ->update(['active' => '1']);
        } else {
            Change_request_statuse::where('cr_id', $cr->id)
                ->where('active', '3')
                ->update(['active' => '2']);

            // Set status based on workflow type with validation
            $targetStatusId = null;
            if ($cr->workflow_type_id === 3) {
                // In-House workflow - return to Business Validation
                $targetStatusId = \App\Services\StatusConfigService::getStatusId('business_validation');
            } elseif ($cr->workflow_type_id === 5) {
                // Vendor workflow - return to Business Analysis
                $targetStatusId = \App\Services\StatusConfigService::getStatusId('business_analysis');
            }

            if ($targetStatusId) {
                // Create new status entry
                $currentStatus = Change_request_statuse::where('cr_id', $cr->id)
                    ->where('active', '1')
                    ->first();

                // Use current status ID as old_status_id, or use target status itself if no current status
                $oldStatusId = $currentStatus ? $currentStatus->new_status_id : $targetStatusId;

                Change_request_statuse::create([
                    'cr_id' => $cr->id,
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $targetStatusId,
                    'user_id' => auth()->id(),
                    'active' => '1',
                ]);
            } else {
                // Fallback to original logic for other workflow types
                if ($cr->workflow_type_id === 3) {
                    $second_status = Change_request_statuse::where('cr_id', $cr->id)
                        ->orderBy('id')
                        ->skip(1)
                        ->first();

                    if ($second_status) {
                        $new_status_work_flow_id = $this->getNewStatusWorkFlowId($cr->workflow_type_id, $second_status->old_status_id, $second_status->new_status_id);

                        $request->merge(['new_status_id' => $new_status_work_flow_id]);
                        $request->merge(['old_status_id' => $second_status->old_status_id]);

                        // Duplicate the row
                        $fallbackStatus = $second_status->replicate();
                        $fallbackStatus->active = '1';
                        $fallbackStatus->save();
                    }
                } else {
                    $firstStatus = Change_request_statuse::where('cr_id', $cr->id)
                        ->orderBy('id', 'asc')
                        ->first();

                    if ($firstStatus) {
                        $new_status_work_flow_id = $this->getNewStatusWorkFlowId($cr->workflow_type_id, $firstStatus->old_status_id, $firstStatus->new_status_id);

                        $request->merge(['new_status_id' => $new_status_work_flow_id]);
                        $request->merge(['old_status_id' => $firstStatus->old_status_id]);

                        // Duplicate the row
                        $fallbackStatus = $firstStatus->replicate();
                        $fallbackStatus->active = '1';
                        $fallbackStatus->save();
                    }
                }
            }
        }

        $request->merge(['hold' => 0]);
        $cr_repo->update($cr->id, $request);

        return $action === 'approve'
            ? "CR #{$cr->cr_no} has been successfully released from hold."
            : "CR #{$cr->cr_no} has been successfully rejected.";
    }

    public function handlePendingCap(Request $request)
    {
        $cr_id = $request->query('crId');
        $workflow = $request->query('workflow');
        $action = $request->query('action');
        $token = $request->query('token');

        if (!$cr_id || !$action || !$token || !$workflow) {
            throw new Exception('Invalid request. Missing parameters.');
        }

        $cr = Change_request::find($cr_id);
        if (!$cr) {
            throw new Exception('Change Request not found.');
        }

        $expectedToken = $this->generateSecurityToken($cr);
        if ($token !== $expectedToken) {
            throw new Exception('Unauthorized access. Invalid token.');
        }

        $current_status = Change_request_statuse::where('cr_id', $cr_id)
            ->where('active', '1')
            ->value('new_status_id');

        if (
            $current_status != StatusConfigService::getStatusId('pending_cab') &&
            $current_status != StatusConfigService::getStatusId('pending_cab_approval')
        ) {
            $message = ($current_status == StatusConfigService::getStatusId('pending_cab_proceed') ||
                $current_status == StatusConfigService::getStatusId('request_vendor_mds'))
                ? 'You already rejected2 this CR.'
                : 'You already approved2 this CR.';
            throw new Exception($message);
        }

        $updateRequest = new Request([
            'old_status_id' => $current_status,
            'new_status_id' => $workflow,
            'cab_cr_flag' => '1',
            'user_id' => auth()->user()->id,
        ]);

        $repo = new ChangeRequestRepository();
        $repo->update($cr_id, $updateRequest);

        return $action === 'approve'
            ? "CR #{$cr_id} has been successfully approved."
            : "CR #{$cr_id} has been successfully rejected.";
    }

    private function getNewStatusWorkFlowId(int $workflow_type_id, int $from, int $to): ?int
    {
        return NewWorkflow::query()
            ->select('new_workflow.id')
            ->join('statuses as s1', function ($join) use ($from) {
                $join->on('s1.id', '=', 'new_workflow.from_status_id')
                    ->where('s1.id', '=', $from);
            })
            ->join('new_workflow_statuses as nws', 'nws.new_workflow_id', '=', 'new_workflow.id')
            ->join('statuses as s2', function ($join) use ($to) {
                $join->on('s2.id', '=', 'nws.to_status_id')
                    ->where('s2.id', '=', $to);
            })
            ->where('new_workflow.type_id', $workflow_type_id)
            ->orderBy('new_workflow.id', 'desc')
            ->value('new_workflow.id');
    }



    public function prepareViewData($cr, int $id): array
    {
        // Get users by workflow type
        $developer_users = $this->getDeveloperUsers($cr);
        $technical_groups = $this->getTechnicalGroups($cr);
        $sa_users = UserFactory::index()->get_user_by_department_id(6);
        $testing_users = UserFactory::index()->get_user_by_department_id(3);
        $cap_users = UserFactory::index()->get_users_cap($cr->application_id);
        $rtm_members = UserFactory::index()->get_user_by_group_id(23);
        $sr_te_validators = UserFactory::index()->get_users_te_validators($cr->application_id);

        // Get technical teams and related data
        $technical_teams = Group::where('technical_team', '1')->get();
        $technical_team_disabled = ChangeRequestTechnicalTeam::where('cr_id', $id)->get();
        $sr_technical_teams = Group::where('sr_technical_team', '1')->get();
        // Get custom fields and other data
        $workflow_type_id = $cr->workflow_type_id;
        $status_id = $cr->getCurrentStatus()->status->id;
        $status_name = $cr->getCurrentStatus()->status->name;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowTypeAndStatus(
            $workflow_type_id,
            3, // FORM_TYPE_VIEW
            $status_id
        );

        $biMappedFieldsNames = [];
        if (in_array($workflow_type_id, [16, 17])) {
            // 1. Get all BI-mapped custom field names
            $allMappedFieldIds = DB::table('bi_request_type_fields')->distinct()->pluck('custom_field_id');
            $allMappedFieldNames = DB::table('custom_fields')->whereIn('id', $allMappedFieldIds)->pluck('name')->toArray();

            // 2. Get the 'type_of_request' value for this CR
            $typeOfRequestField = $cr->change_request_custom_fields
                ->where('custom_field_name', 'type_of_request')
                ->first();

            if ($typeOfRequestField && !empty($typeOfRequestField->custom_field_value)) {
                $requestTypeId = $typeOfRequestField->custom_field_value;

                // 3. Get fields for THIS specific request type
                $shownFieldIds = DB::table('bi_request_type_fields')
                    ->where('request_type_id', $requestTypeId)
                    ->pluck('custom_field_id');

                $shownFieldNames = DB::table('custom_fields')->whereIn('id', $shownFieldIds)->pluck('name')->toArray();

                // 4. Fields to HIDE are (all - shown)
                $biMappedFieldsNames = array_diff($allMappedFieldNames, $shownFieldNames);
            } else {
                // If no request type is selected, hide ALL BI-mapped fields
                $biMappedFieldsNames = $allMappedFieldNames;
            }
        }

        $logs_ers = $cr->logs->load('user:id,user_name,default_group', 'user.defualt_group:id,title');
        $all_defects = $this->defects->all_defects($id);
        $ApplicationImpact = ApplicationImpact::where('application_id', $cr->application_id)
            ->select('impacts_id')
            ->get();

        // Get technical team data
        $selected_technical_teams = $this->getSelectedTechnicalTeams($cr);
        $selected_sr_technical_teams = $this->getSelectedSrTechnicalTeams($cr);

        $reminder_promo_tech_teams = $this->getReminderPromoTechTeams($cr);
        $reminder_promo_tech_teams_text = implode(',', $reminder_promo_tech_teams);
        // Get assignment users
        $view_by_groups = $cr->getCurrentStatus()->status->group_statuses
            ->where('type', '2')
            ->pluck('group_id')
            ->toArray();
        $assignment_users = UserFactory::index()->GetAssignmentUsersByViewGroups($view_by_groups);

        $man_day = $cr->change_request_custom_fields
            ->where('custom_field_name', 'man_days')
            ->values()
            ->toArray();
        $reject = new RejectionReasonsRepository();
        $rejects = $reject->workflows($workflow_type_id);

        $man_days = ManDaysLog::where('cr_id', $id)->with('user')->get();
        $relevantField = $cr->change_request_custom_fields
            ->where('custom_field_name', 'relevant')
            ->first();

        // Step 2: decode the JSON values into selected CR IDs
        $selectedRelevant = [];
        if ($relevantField && !empty($relevantField->custom_field_value)) {
            $decoded = json_decode($relevantField->custom_field_value, true);
            if (is_array($decoded)) {
                $selectedRelevant = array_map('intval', $decoded);
            }
        }

        // Step 3: load these CRs with status
        $relevantCrsData = \App\Models\Change_request::whereIn('id', $selectedRelevant)
            ->with('CurrentRequestStatuses')
            ->get(['id', 'cr_no', 'title']);

        // Step 4: check if any relevant CR is not in Pending Production Deployment
        $pendingProductionId = StatusConfigService::getStatusId('pending_production_deployment');
        $relevantNotPending = $relevantCrsData->filter(function ($item) use ($pendingProductionId) {
            return $item->CurrentRequestStatuses_last?->new_status_id != $pendingProductionId;
        })->count();

        // Get CRs that can be depended upon
        $dependableCrs = Change_request::getDependableCrs($cr->id);

        return compact(
            'rejects',
            'selected_technical_teams',
            'selected_sr_technical_teams',
            'man_day',
            'technical_team_disabled',
            'status_name',
            'ApplicationImpact',
            'cap_users',
            'CustomFields',
            'cr',
            'workflow_type_id',
            'logs_ers',
            'developer_users',
            'sa_users',
            'testing_users',
            'sr_technical_teams',
            'technical_teams',
            'all_defects',
            'reminder_promo_tech_teams',
            'rtm_members',
            'assignment_users',
            'reminder_promo_tech_teams_text',
            'technical_groups',
            'man_days',
            'relevantCrsData',
            'relevantNotPending',
            'pendingProductionId',
            'sr_te_validators',
            'dependableCrs',
            'biMappedFieldsNames'
        );
    }

}
