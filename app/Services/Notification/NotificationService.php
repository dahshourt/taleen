<?php

namespace App\Services\Notification;

use App\Models\Change_request;
use App\Models\DeploymentApprovalCr;
use App\Models\DeploymentApprovalCrUser;
use App\Models\NotificationRule;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicNotification;
use App\Models\User;
use App\Models\Group;
use App\Models\TechnicalCr;
use Illuminate\Support\Str;

class NotificationService
{
    private $toMailUser;
    private $toMailGroup;
    public function handleEvent($event)
    {
        $eventClass = get_class($event);

        // Get all active rules for this event
        $rules = NotificationRule::with(['template', 'recipients'])
            ->where('event_class', $eventClass)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        //dd($event->changeRequest->application->group_applications->last()->group_id);
        foreach ($rules as $rule) {
            // Check if conditions match
            if ($this->evaluateConditions($rule, $event)) {
                //dd($rule);
                $this->processNotification($rule, $event);
            }
        }
    }

    protected function evaluateConditions($rule, $event)
    {

        if (empty($rule->conditions)) {
            return true; // No conditions = always execute
        }

        $conditions = $rule->conditions;

        // Check workflow_type condition
        if (isset($conditions['workflow_type'])) {
            if ($event->changeRequest->workflow_type_id != $conditions['workflow_type']) {
                return false;
            }
        }

        // Check workflow_type_not condition
        if (isset($conditions['workflow_type_not'])) {
            if ($event->changeRequest->workflow_type_id == $conditions['workflow_type_not']) {
                return false;
            }
        }

        // Check new_status_id (for status update events)
        if (isset($conditions['new_status_id'])) {
            if (!isset($event->newStatusIds) || !in_array($conditions['new_status_id'], $event->newStatusIds)) {
                return false;
            }
        }
        if (isset($conditions['old_status_id'])) {
            if ($event->request->old_status_id != $conditions['old_status_id']) {
                return false;
            }
        }

        // Check custom_field condition (for checking custom field values like need_design)
        if (isset($conditions['custom_field'])) {
            $fieldName = $conditions['custom_field']['name'] ?? null;
            $expectedValue = $conditions['custom_field']['value'] ?? null;

            if ($fieldName && $expectedValue !== null) {
                $customField = $event->changeRequest->change_request_custom_fields()
                    ->where('custom_field_name', $fieldName)
                    ->first();

                $actualValue = $customField?->custom_field_value;

                if ($actualValue != $expectedValue) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function processNotification($rule, $event)
    {
        // Check active_flag for status update events - only send if status is active
        if (isset($event->active_flag) && $event->active_flag != '1') {
            return; // Skip notification if status is not active
        }

        // Resolve recipients (get the recipients that will receive the notification)
        $recipients = $this->resolveRecipients($rule, $event);
        //dd($recipients);

        if (empty($recipients['to'])) {
            return; // No recipients no notification will be sent
        }

        // get the template the will be sent
        $rendered = $this->renderTemplate($rule->template, $event, $rule);

        // create log entry
        $log = $this->createLog($rule, $event, $recipients, $rendered);

        // Send email (queued)
        try {
            Mail::to($recipients['to'])
                ->cc($recipients['cc'] ?? [])
                ->bcc($recipients['bcc'] ?? [])
                ->queue(new DynamicNotification($rendered['subject'], $rendered['body']));

            $log->update(['status' => 'queued']);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    /*protected function resolveRecipients($rule, $event)
    {
        $resolved = ['to' => [], 'cc' => [], 'bcc' => []];

        foreach ($rule->recipients as $recipient) {
            $emails = $this->getRecipientEmails($recipient, $event);
            $resolved[$recipient->channel] = array_merge(
                $resolved[$recipient->channel],
                $emails
            );
        }

        return $resolved;
    }*/

    protected function resolveRecipients($rule, $event)
    {
        $resolved = ['to' => [], 'cc' => [], 'bcc' => []];

        foreach ($rule->recipients as $recipient) {
            $emails = $this->getRecipientEmails($recipient, $event);

            $resolved[$recipient->channel] = array_merge(
                $resolved[$recipient->channel],
                $emails
            );
        }


        if (
            $event instanceof \App\Events\ChangeRequestCreated &&
            (int) $event->changeRequest->application_id == 88
        ) {
            $resolved['bcc'][] = 'Ticketing.DEV@te.eg';
        }

        $resolved['bcc'] = array_unique($resolved['bcc']);

        return $resolved;
    }



    // the recipients emails
    protected function getRecipientEmails($recipient, $event)
    {
        switch ($recipient->recipient_type) {
            case 'cr_creator':
                $this->toMailUser = $event->creator->email ?? $event->changeRequest->requester_email;
                return [$event->creator->email ?? $event->changeRequest->requester_email];

            case 'division_manager':
                // Get division manager from statusData or from CR model
                if (isset($event->statusData['division_manager'])) {
                    $this->toMailUser = $event->statusData['division_manager'];
                    return [$event->statusData['division_manager']];
                }
                // get it from the model
                if (isset($event->changeRequest->division_manager_id)) {
                    $dm = $event->changeRequest->division_manager;
                    return $dm ? [$dm->email] : [];
                }
                return [];
            // in case sent to specific email

            case 'dm_bcc':
                //$this->toMailUser = $recipient->recipient_identifier;
                return config('constants.division_managers_mails');

            case 'static_email':
                $this->toMailUser = $recipient->recipient_identifier;
                return [$recipient->recipient_identifier];

            case 'user':
                $user = User::find($recipient->recipient_identifier);
                $this->toMailUser = $user->email;
                return $user ? [$user->email] : [];
            // need to review this
            case 'group':
                $group = Group::find($recipient->recipient_identifier);
                $this->toMailUser = $group->head_group_email;
                return $group ? [$group->head_group_email] : [];

            case 'developer':
                if (isset($event->request->developer_id)) {
                    $dev = User::find($event->request->developer_id);
                    $this->toMailUser = $dev->email;
                    return $dev ? [$dev->email] : [];
                } else {
                    $dev = $event->changeRequest->resDeveloper->user->email ?? null;
                    $this->toMailUser = $dev;
                    return $dev ? [$dev] : [];
                }
                return [];

            case 'tester':
                if (isset($event->request->tester_id)) {
                    $tester = User::find($event->request->tester_id);
                    $this->toMailUser = $tester->email;
                    return $tester ? [$tester->email] : [];
                } else {
                    $tester = $event->changeRequest->resTester->user->email ?? null;
                    $this->toMailUser = $tester;
                    return $tester ? [$tester] : [];
                }
                return [];

            case 'designer':
                if (isset($event->request->designer_id)) {
                    $designer = User::find($event->request->designer_id);
                    $this->toMailUser = $designer->email;
                    return $designer ? [$designer->email] : [];
                } else {
                    $designer = $event->changeRequest->resDesigner->user->email ?? null;
                    $this->toMailUser = $designer;
                    return $designer ? [$designer] : [];
                }
                return [];

            case 'cr_member':
                if (isset($event->request->cr_member)) {
                    $cr_member = User::find($event->request->cr_member);
                    $this->toMailUser = $cr_member->email;
                    return $cr_member ? [$cr_member->email] : [];
                } else {
                    $cr_member = $event->changeRequest->resCrMember->user->email ?? null;
                    $this->toMailUser = $cr_member;
                    return $cr_member ? [$cr_member] : [];
                }
                return [];
            case 'operation_manager':
                if (property_exists($event, 'changeRequest') && $event->changeRequest && $event->changeRequest->application_id) {
                    $application = \App\Models\Application::find($event->changeRequest->application_id);
                    if ($application && $application->operation_dm) {
                        $operation_manager = User::find($application->operation_dm);
                        if ($operation_manager && $operation_manager->email) {
                            $this->toMailUser = $operation_manager->email;
                            return [$operation_manager->email];
                        }
                    }
                }
                return [];
            case 'cr_managers':
                return config('constants.cr_managers_mails');

            case 'cr_team':
                $group = Group::where('title', config('constants.group_names.cr_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.cr_team')];

            case 'qc_team':
                $group = Group::where('title', config('constants.group_names.qc_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.qc_team')];

            case 'sa_team':
                $group = Group::where('title', config('constants.group_names.sa_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.sa_team')];

            case 'as_team':
                $group = Group::where('title', config('constants.group_names.as_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.as_team')];

            case 'bo_team':
                $group = Group::where('title', config('constants.group_names.bo_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.bo_team')];

            case 'qa_team':
                $group = Group::where('title', config('constants.group_names.qa_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.qa_team')];

            case 'uat_team':
                $group = Group::where('title', config('constants.group_names.uat_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.uat_team')];

            case 'pmo_team':
                $group = Group::where('title', config('constants.group_names.pmo_team'))->first();
                return $group ? [$group->head_group_email] : [config('constants.mails.pmo_team')];
            case 'cap_users':
                // Try to load active CAB users directly from cab_cr_users table for this CR
                if (property_exists($event, 'changeRequest') && $event->changeRequest && isset($event->changeRequest->id)) {
                    $emails = \Illuminate\Support\Facades\DB::table('cab_cr_users')
                        ->join('cab_crs', 'cab_crs.id', '=', 'cab_cr_users.cab_cr_id')
                        ->join('users', 'users.id', '=', 'cab_cr_users.user_id')
                        ->where('cab_crs.cr_id', $event->changeRequest->id)
                        ->whereRaw('CAST(cab_crs.status AS CHAR) = ?', ['1'])
                        ->whereRaw('CAST(cab_cr_users.status AS CHAR) = ?', ['1'])
                        ->where('users.active', '1')
                        ->pluck('users.email')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    if (!empty($emails)) {
                        $this->toMailUser = $emails[0];
                        return $emails;
                    }
                }

                // Fallback: If cap users were provided in the event/request (e.g. during update), use them
                if (property_exists($event, 'request') && isset($event->request->cap_users) && is_array($event->request->cap_users) && !empty($event->request->cap_users)) {
                    $emails = \App\Models\User::whereIn('id', $event->request->cap_users)->where('active', '1')->pluck('email')->filter()->values()->toArray();
                    if (!empty($emails)) {
                        $this->toMailUser = $emails[0] ?? null;
                        return $emails;
                    }
                }

                return [];

            case 'assigned_dev_team':
                // Get the group assigned to handle the CR

                $group = Group::find(
                    $event->changeRequest->change_request_custom_fields()
                        ->where('custom_field_name', 'tech_group_id')
                        ->value('custom_field_value')
                );
                return $group ? [$group->head_group_email] : [];

            /*if (isset($event->changeRequest->application_id)) {
                $group_id = $event->changeRequest->application->group_applications->last()->group_id;
                $group = Group::find($group_id);
                return $group ? [$group->head_group_email] : [];
            }
            return [];*/
            case 'tech_teams':
                if (!empty($event->request->technical_teams) && is_array($event->request->technical_teams)) {
                    return Group::whereIn('id', $event->request->technical_teams)
                        ->pluck('head_group_email')
                        ->filter()
                        ->values()
                        ->toArray();
                } else {
                    $tech_cr = TechnicalCr::with('technical_cr_team')
                        ->where('cr_id', $event->changeRequest->id)
                        ->latest()
                        ->first();

                    $tech_team = $tech_cr->technical_cr_team->pluck('group_id')->toArray();

                    return Group::whereIn('id', $tech_team)
                        ->pluck('head_group_email')
                        ->filter()
                        ->values()
                        ->toArray();
                }
                return [];

            // MDS for MDS notifications
            case 'mds_group':
                // Get group from MDS event
                if ($event instanceof \App\Events\MdsStartDateUpdated) {
                    $group = Group::find($event->groupId);
                    $this->toMailGroup = $group->head_group_email ?? null;
                    return $group ? [$group->head_group_email] : [];
                }
                return [];

            // Defect group - for defect notifications
            case 'defect_group':
                if ($event instanceof \App\Events\DefectCreated || $event instanceof \App\Events\DefectStatusUpdated) {
                    $group = Group::find($event->groupId);
                    $this->toMailGroup = $group->head_group_email ?? null;
                    return $group ? [$group->head_group_email] : [];
                }
                return [];

            // Prerequisite group - for prerequisite/assistance request notifications
            case 'prerequisite_group':
                if ($event instanceof \App\Events\PrerequisiteCreated || $event instanceof \App\Events\PrerequisiteStatusUpdated) {
                    $group = Group::find($event->groupId);
                    $this->toMailGroup = $group->head_group_email ?? null;
                    return $group ? [$group->head_group_email] : [];
                }
                return [];

            case 'sr_te_validators':
                if (property_exists($event, 'request') && isset($event->request->sr_te_validators) && is_array($event->request->sr_te_validators)) {
                    $emails = \App\Models\User::whereIn('id', $event->request->sr_te_validators)
                        ->pluck('email')
                        ->filter()
                        ->values()
                        ->toArray();
                        
                    if (!empty($emails)) {
                        $this->toMailUser = $emails[0] ?? null;
                        return $emails;
                    }
                }
                return [];

            case 'approved_by':
                if (property_exists($event, 'changeRequest') && $event->changeRequest) {
                    $approvedByName = $event->changeRequest->change_request_custom_fields()
                        ->where('custom_field_name', 'approved_by')
                        ->value('custom_field_value');

                    if ($approvedByName) {
                        $user = \App\Models\User::where('user_name', $approvedByName)->first();
                        if ($user && $user->email) {
                            $this->toMailUser = $user->email;
                            return [$user->email];
                        }
                    }
                }
                return [];

            // Add more types as needed
            default:
                return [];
        }
    }

    protected function renderTemplate($template, $event, $rule)
    {
        $placeholders = $this->extractPlaceholders($event, $rule);

        $subject = $this->replacePlaceholders($template->subject, $placeholders);
        $body = $this->replacePlaceholders($template->body, $placeholders);

        return compact('subject', 'body');
    }

    protected function extractPlaceholders($event, $rule)
    {
        // Handle defect events differently
        $isDefectEvent = $event instanceof \App\Events\DefectCreated || $event instanceof \App\Events\DefectStatusUpdated;

        if ($isDefectEvent) {
            return $this->extractDefectPlaceholders($event, $rule);
        }

        // Handle prerequisite events
        $isPrerequisiteEvent = $event instanceof \App\Events\PrerequisiteCreated || $event instanceof \App\Events\PrerequisiteStatusUpdated;

        if ($isPrerequisiteEvent) {
            return $this->extractPrerequisitePlaceholders($event, $rule);
        }

        // Handle hold events
        $isHoldEvent = $event instanceof \App\Events\ChangeRequestOnHold;

        if ($isHoldEvent) {
            return $this->extractHoldPlaceholders($event, $rule);
        }

        // Extract data from event (for CR events)
        $cr = property_exists($event, 'changeRequest') ? $event->changeRequest : null;
        $statusData = $event->statusData ?? [];

        // Get creator/requester name
        $creatorName = 'User';
        if (property_exists($event, 'creator') && $event->creator && isset($event->creator->user_name)) {
            $creatorName = $event->creator->user_name;
        } elseif (isset($statusData['requester_name'])) {
            $creatorName = $statusData['requester_name'];
        } elseif ($cr && isset($cr->requester_name)) {
            $creatorName = $cr->requester_name;
        }

        // Extract first name from email if available
        $firstName = $this->toMailUser;
        $email_parts = explode('.', explode('@', $this->toMailUser)[0]);
        $firstName = ucfirst($email_parts[0]);

        if (empty($firstName)) {
            $firstName = "Team";
        }

        /*
        if ($event->creator && isset($event->creator->email)) {
            $email_parts = explode('.', explode('@', $event->creator->email)[0]);
            $firstName = ucfirst($email_parts[0]);
        } elseif (isset($statusData['requester_email'])) {
            $email_parts = explode('.', explode('@', $statusData['requester_email'])[0]);
            $firstName = ucfirst($email_parts[0]);
        }
        */

        // Get division manager name if available
        $divisionManagerName = '';
        $divisionManagerEmail = '';
        if (isset($statusData['division_manager'])) {
            $divisionManagerEmail = $statusData['division_manager'];
            $email_parts = explode('.', explode('@', $divisionManagerEmail)[0]);
            $divisionManagerName = ucfirst($email_parts[0]);
        }

        // CR link based on workflow type
        $crLink = route('show.cr', $cr->id);
        $systemLink = url('/');
        // if the rule is notify devision manager
        if ($rule->name == config('constants.rules.notify_division_manager_default')) {
            $crLink = route('edit.cr', ['id' => $cr->id, 'check_dm' => 1]);
        }

        $applicationName = $cr->application->name ?? '';

        // Get QC email (RPA) and ticketing dev from config
        $qcEmail = config('constants.mails.qc_mail', '');
        $serviceAccount = config('services.ews.username', '');
        $ticketingDev = config('constants.mails.ticketing_dev_mail', '');
        $replyToEmail = config('mail.from.address', '');
        $subject = "Re: CR #{$cr->cr_no} - Awaiting Your Approval";

        $approveLink = "mailto:{$replyToEmail}?subject=" . rawurlencode($subject) . "&cc={$ticketingDev}&body=approved";
        $rejectLink = "mailto:{$replyToEmail}?subject=" . rawurlencode($subject) . "&cc={$ticketingDev}&body=rejected";

        // Operation DM and Capacity Approval Subject and Links
        $general_sa = config('constants.service_accounts_emails.general_sa', '');
        $operationDmCapacitySubject = "Re: [TMS] CR #{$cr->cr_no} - Awaiting Operation DM & Capacity Approval";
        $operationDmCapacityApproveLink = "mailto:{$general_sa}?subject=" . rawurlencode($operationDmCapacitySubject) . "&cc={$ticketingDev}&body=approved";

        $rejectDisclaimer = "\n\n*** DISCLAIMER: If you Select Reject, the Production deployment will be delayed. This is due to the need to reschedule the deployment date and time ***";
        $operationDmCapacityRejectLink = "mailto:{$general_sa}?subject=" . rawurlencode($operationDmCapacitySubject) . "&cc={$ticketingDev}&body=rejected" . rawurlencode($rejectDisclaimer);

        // Eng. Walaa Amin Approval Subject and Links
        $walaa_amin_sa = config('constants.service_accounts_emails.general_sa', '');
        $walaaAminSubject = "Re: [TMS] CR #{$cr->cr_no} - Awaiting Eng. Walaa Amin Approval";
        $walaaAminApproveLink = "mailto:{$walaa_amin_sa}?subject=" . rawurlencode($walaaAminSubject) . "&cc={$ticketingDev}&body=approved";
        $walaaAminRejectLink = "mailto:{$walaa_amin_sa}?subject=" . rawurlencode($walaaAminSubject) . "&cc={$ticketingDev}&body=rejected" . rawurlencode($rejectDisclaimer);

        // Director Approval Subject and Links
        $director_sa = config('constants.service_accounts_emails.general_sa', '');
        $directorSubject = "Re: [TMS] CR #{$cr->cr_no} - Awaiting Director Approval";
        $directorApproveLink = "mailto:{$director_sa}?subject=" . rawurlencode($directorSubject) . "&cc={$ticketingDev}&body=approved";
        $directorRejectLink = "mailto:{$director_sa}?subject=" . rawurlencode($directorSubject) . "&cc={$ticketingDev}&body=rejected" . rawurlencode($rejectDisclaimer);

        // SR Approval Subject and Links
        $sr_sa = config('constants.service_accounts_emails.general_sa', '');
        $srSubject = "Re: [TMS] SR #{$cr->cr_no} - Awaiting SR Approval";
        $srApproveLink = "mailto:{$sr_sa}?subject=" . rawurlencode($srSubject) . "&cc={$ticketingDev}&body=approved";
        $srRejectLink = "mailto:{$sr_sa}?subject=" . rawurlencode($srSubject) . "&cc={$ticketingDev}&body=rejected" . rawurlencode($rejectDisclaimer);

        // Get old and new status names for status update events
        $oldStatus = '';
        $newStatus = '';
        $currentStatus = '';

        if (isset($statusData['old_status_id'])) {
            $oldStatusModel = \App\Models\Status::find($statusData['old_status_id']);
            $oldStatus = $oldStatusModel->status_name ?? '';
        } elseif (property_exists($event, 'request') && $event->request && isset($event->request->old_status_id)) {
            $oldStatusModel = \App\Models\Status::find($event->request->old_status_id);
            $oldStatus = $oldStatusModel->status_name ?? '';
        }

        $currentStatus = $oldStatus;

        // For new status, use the specific status from the rule condition (not all statuses)
        // This shows only the relevant status that triggered this notification
        $conditions = $rule->conditions ?? [];
        if (isset($conditions['new_status_id'])) {
            // Use the specific status from the condition
            $newStatusModel = \App\Models\Status::find($conditions['new_status_id']);
            $newStatus = $newStatusModel->status_name ?? '';
        } elseif (isset($event->newStatusIds) && !empty($event->newStatusIds)) {
            // Fallback: use first status from workflow if no condition specified
            $newStatusModel = \App\Models\Status::whereIn('id', $event->newStatusIds)->first();
            $newStatus = $newStatusModel->status_name ?? '';
        }

        // Get group name for group notifications
        $groupName = '';
        if (isset($cr->application_id)) {
            $app = $cr->application;
            if ($app && $app->group_applications->first()) {
                $group = $app->group_applications->first()->group;
                $groupName = $group ? $group->title : '';
            }
        }

        $kickoff_meeting_date = $cr->change_request_custom_fields
            ->where('custom_field_name', 'kick_off_meeting_date')
            ->first()
            ->custom_field_value ?? null;

        $deployment_date_time = $cr->change_request_custom_fields
            ->where('custom_field_name', 'deployment_date_time')
            ->first()
            ->custom_field_value ?? null;
        $deployment_end_date_time = $cr->change_request_custom_fields
            ->where('custom_field_name', 'deployment_end_date_time')
            ->first()
            ->custom_field_value ?? null;
        $need_down_time = $cr->change_request_custom_fields
            ->where('custom_field_name', 'need_down_time')
            ->first()
            ->custom_field_value ?? null;

        return [
            'cr_no' => $cr->cr_no,
            'cr_id' => $cr->id,
            'cr_title' => $statusData['title'] ?? $cr->title ?? '',
            'cr_description' => $statusData['description'] ?? $cr->description ?? '',
            'creator_name' => $creatorName,
            'requester_name' => $creatorName,
            'first_name' => $firstName,
            'division_manager_name' => $divisionManagerName,
            'current_status' => $currentStatus,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'group_name' => $groupName,
            'cr_link' => $crLink,
            'approve_link' => $approveLink,
            'reject_link' => $rejectLink,
            'workflow_type_id' => $cr->workflow_type_id ?? '',
            'system_link' => $systemLink,
            'start_cr_date' => $cr->start_CR_time,
            'start_sa_date' => $cr->start_design_time,
            'kickoff_meeting_date' => $kickoff_meeting_date,
            'application_name' => $applicationName,
            'operation_dm_capacity_approve_link' => $operationDmCapacityApproveLink,
            'operation_dm_capacity_reject_link' => $operationDmCapacityRejectLink,
            'walaa_amin_approve_link' => $walaaAminApproveLink,
            'walaa_amin_reject_link' => $walaaAminRejectLink,
            'director_approve_link' => $directorApproveLink,
            'director_reject_link' => $directorRejectLink,
            'deployment_date' => $deployment_date_time,
            'deployment_end_date' => $deployment_end_date_time,
            'need_down_time' => $need_down_time,
            'sr_approval_link' => $srApproveLink,
            'sr_reject_link' => $srRejectLink,

            // MDS-specific placeholders
            'mds_start_date' => $event->newStartDate ?? '',
            'mds_old_start_date' => $event->oldStartDate ?? '',
            'mds_end_date' => $event->mdsLog->end_date ?? '',
            'mds_man_days' => $event->mdsLog->man_day ?? '',
            'mds_group_name' => isset($event->groupId) ? (Group::find($event->groupId)?->title ?? '') : '',
        ];
    }

    protected function extractDefectPlaceholders($event, $rule)
    {
        $defect = $event->defect;
        $group = Group::find($event->groupId);
        $cr = $defect->change_request;

        // Get status names
        $currentStatus = $defect->current_status->status_name ?? '';
        $oldStatus = '';
        $newStatus = '';

        if ($event instanceof \App\Events\DefectStatusUpdated) {
            $oldStatusModel = \App\Models\Status::find($event->oldStatusId);
            $newStatusModel = \App\Models\Status::find($event->newStatusId);
            $oldStatus = $oldStatusModel->status_name ?? '';
            $newStatus = $newStatusModel->status_name ?? '';
        }

        // Get first name from group email
        $firstName = 'Team';
        if ($this->toMailGroup) {
            $email_parts = explode('.', explode('@', $this->toMailGroup)[0]);
            $firstName = ucfirst($email_parts[0]);
        }

        $defectLink = route('defect.show', $defect->id);
        $systemLink = url('/');

        return [
            // Defect-specific placeholders
            'defect_id' => $defect->id,
            'defect_subject' => $defect->subject ?? '',
            'defect_status' => $currentStatus,
            'defect_old_status' => $oldStatus,
            'defect_new_status' => $newStatus,
            'defect_group_name' => $group->title ?? '',
            'defect_cr_no' => $cr->cr_no ?? '',
            'defect_cr_title' => $cr->title ?? '',
            'defect_link' => $defectLink,

            // Common placeholders
            'first_name' => $firstName,
            'group_name' => $group->title ?? '',
            'system_link' => $systemLink,

            // CR placeholders (for context)
            'cr_no' => $cr->cr_no ?? '',
            'cr_title' => $cr->title ?? '',
            'cr_link' => $cr ? route('show.cr', $cr->id) : '',
        ];
    }

    protected function extractPrerequisitePlaceholders($event, $rule)
    {
        $prerequisite = $event->prerequisite;
        $group = Group::find($event->groupId);
        $cr = $prerequisite->promo; // promo is the related CR

        // Get status names
        $currentStatus = $prerequisite->status->status_name ?? '';
        $oldStatus = '';
        $newStatus = '';

        if ($event instanceof \App\Events\PrerequisiteStatusUpdated) {
            $oldStatusModel = \App\Models\Status::find($event->oldStatusId);
            $newStatusModel = \App\Models\Status::find($event->newStatusId);
            $oldStatus = $oldStatusModel->status_name ?? '';
            $newStatus = $newStatusModel->status_name ?? '';
        }

        // Get first name from group email
        $firstName = 'Team';
        if ($this->toMailGroup) {
            $email_parts = explode('.', explode('@', $this->toMailGroup)[0]);
            $firstName = ucfirst($email_parts[0]);
        }

        $prerequisiteLink = route('prerequisites.show', $prerequisite->id);
        $systemLink = url('/');

        return [
            // Prerequisite-specific placeholders
            'prerequisite_id' => $prerequisite->id,
            'prerequisite_subject' => $prerequisite->subject ?? '',
            'prerequisite_status' => $currentStatus,
            'prerequisite_old_status' => $oldStatus,
            'prerequisite_new_status' => $newStatus,
            'prerequisite_group_name' => $group->title ?? '',
            'prerequisite_cr_no' => $cr->cr_no ?? '',
            'prerequisite_cr_title' => $cr->title ?? '',
            'prerequisite_link' => $prerequisiteLink,

            // Common placeholders
            'first_name' => $firstName,
            'group_name' => $group->title ?? '',
            'system_link' => $systemLink,

            // CR placeholders (for context - promo)
            'cr_no' => $cr->cr_no ?? '',
            'cr_title' => $cr->title ?? '',
            'cr_link' => $cr ? route('show.cr', $cr->id) : '',
        ];
    }

    protected function extractHoldPlaceholders($event, $rule)
    {
        $cr = $event->changeRequest;
        $holdData = $event->holdData ?? [];
        $holdReason = $event->holdReason;

        // Get first name from recipient email
        $firstName = 'Team';
        if ($this->toMailUser) {
            $email_parts = explode('.', explode('@', $this->toMailUser)[0]);
            $firstName = ucfirst($email_parts[0]);
        }

        // Get who put the CR on hold
        $holdBy = 'System';
        if (auth()->check()) {
            $holdBy = auth()->user()->user_name ?? auth()->user()->name ?? 'System';
        }

        // Get current status name before hold
        $currentStatus = '';
        $currentStatusRecord = $cr->currentRequestStatuses;
        if ($currentStatusRecord) {
            $statusModel = \App\Models\Status::find($currentStatusRecord->new_status_id);
            $currentStatus = $statusModel->status_name ?? '';
        }

        $crLink = route('show.cr', $cr->id);
        $systemLink = url('/');
        $applicationName = $cr->application->name ?? '';

        return [
            // CR placeholders
            'cr_no' => $cr->cr_no,
            'cr_id' => $cr->id,
            'cr_title' => $cr->title ?? '',
            'cr_description' => $cr->description ?? '',
            'cr_link' => $crLink,
            'application_name' => $applicationName,
            'current_status' => $currentStatus,

            // Hold-specific placeholders
            'hold_reason' => $holdReason->name ?? 'N/A',
            'resuming_date' => $holdData['resuming_date'] ?? 'N/A',
            'justification' => $holdData['justification'] ?? 'N/A',
            'hold_by' => $holdBy,

            // Common placeholders
            'first_name' => $firstName,
            'creator_name' => $event->creator->user_name ?? 'User',
            'requester_name' => $event->creator->user_name ?? 'User',
            'system_link' => $systemLink,
        ];
    }

    protected function replacePlaceholders($text, $placeholders)
    {
        foreach ($placeholders as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    protected function createLog($rule, $event, $recipients, $rendered)
    {
        // Determine related model based on event type
        $isDefectEvent = $event instanceof \App\Events\DefectCreated || $event instanceof \App\Events\DefectStatusUpdated;
        $isPrerequisiteEvent = $event instanceof \App\Events\PrerequisiteCreated || $event instanceof \App\Events\PrerequisiteStatusUpdated;

        if ($isDefectEvent) {
            $relatedModelType = \App\Models\Defect::class;
            $relatedModelId = $event->defect->id;
            $eventData = ['defect_id' => $event->defect->id];
        } elseif ($isPrerequisiteEvent) {
            $relatedModelType = \App\Models\Prerequisite::class;
            $relatedModelId = $event->prerequisite->id;
            $eventData = ['prerequisite_id' => $event->prerequisite->id];
        } else {
            $relatedModelType = property_exists($event, 'changeRequest') ? get_class($event->changeRequest) : null;
            $relatedModelId = $event->changeRequest->id ?? null;
            $eventData = ['cr_id' => $event->changeRequest->id ?? null];
        }

        return NotificationLog::create([
            'notification_rule_id' => $rule->id,
            'template_id' => $rule->template_id,
            'event_class' => get_class($event),
            'event_data' => $eventData,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'recipients_to' => $recipients['to'],
            'recipients_cc' => $recipients['cc'] ?? [],
            'recipients_bcc' => $recipients['bcc'] ?? [],
            'status' => 'pending',
            'related_model_type' => $relatedModelType,
            'related_model_id' => $relatedModelId,
        ]);
    }

    public function notifyOperationalDM(Change_request $cr): void
    {
        $notification_template = NotificationTemplate::firstWhere('name', 'LIKE', '%Operation DM and Capacity Approval%');

        $dm_users = DeploymentApprovalCrUser::whereHas('deploymentApprovalCr', function ($query) use ($cr) {
            $query->whereIn('status', [DeploymentApprovalCrUser::INACTIVE, DeploymentApprovalCrUser::REJECTED])
                ->where('cr_id', $cr->id);
        })
        ->with('user:id,email')
        ->get();

        $users_emails = $dm_users->pluck('user.email')->toArray();

        if ($notification_template && count($users_emails)) {

            $ticketingDev = config('constants.mails.ticketing_dev_mail', '');

            // Operation DM and Capacity Approval Subject and Links
            $general_sa = config('constants.service_accounts_emails.general_sa', '');
            $operationDmCapacitySubject = "Re: [TMS] CR #{$cr->cr_no} - Awaiting Operation DM & Capacity Approval";
            $operationDmCapacityApproveLink = "mailto:{$general_sa}?subject=" . rawurlencode($operationDmCapacitySubject) . "&cc={$ticketingDev}&body=approved";

            $rejectDisclaimer = "\n\n*** DISCLAIMER: If you Select Reject, the Production deployment will be delayed. This is due to the need to reschedule the deployment date and time ***";
            $operationDmCapacityRejectLink = "mailto:{$general_sa}?subject=" . rawurlencode($operationDmCapacitySubject) . "&cc={$ticketingDev}&body=rejected" . rawurlencode($rejectDisclaimer);

            $all_data = [
                'cr_no' => $cr->cr_no,
                'cr_title' => $cr->title,
                'operation_dm_capacity_approve_link' => $operationDmCapacityApproveLink,
                'operation_dm_capacity_reject_link' => $operationDmCapacityRejectLink,
                'system_link' => route('change_request.show', $cr->id),
            ];

            foreach ($users_emails as $email) {
                $first_name = Str::of($email)->before('@')->before('.')->title();
                $all_data['first_name'] = $first_name;

                $subject = $this->renderUsingRegex($notification_template->subject, $all_data);
                $body = $this->renderUsingRegex($notification_template->body, $all_data);

                Mail::to($email)
                    ->queue(new DynamicNotification($subject, $body));

                Log::info("DM Approval mail sent to $email using the button in shows page");
            }
        }
    }

    private function renderUsingRegex(string $content, array $placeholders = []): string
    {
        $data_with_colon = preg_replace('/#?\{\{\s*(\w+)\s*\}\}/', ':$1', $content);

        return trans($data_with_colon, $placeholders);
    }

}
