<?php

namespace App\Http\Repository\Logs;

use App\Contracts\Logs\LogRepositoryInterface;
// declare Entities
use App\Models\Application;
use App\Models\Category;
use App\Models\Change_request;
use App\Models\CustomField;
use App\Models\DeploymentImpact;
use App\Models\DivisionManagers;
use App\Models\HoldReason;
use App\Models\Log;
use App\Models\NeedDownTime;
use App\Models\NewWorkFlow;
use App\Models\NewWorkFlowStatuses;
use App\Models\Priority;
use App\Models\Rejection_reason;
use App\Models\Status;
use App\Models\Unit;
use App\Models\User;
use Auth;
use Illuminate\Support\Str;

class LogRepository implements LogRepositoryInterface
{
    private string $log_prefix = 'Change Request';

    public function getAll()
    {
        return Log::all();
    }

    public function create($request)
    {
        return Log::create($request);
    }

    public function delete($id)
    {
        return Log::destroy($id);
    }

    public function update($request, $id)
    {
        return Log::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Log::find($id);
    }

    public function get_by_cr_id($id)
    {
        return Log::where('cr_id', $id)->get();
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }

    public function logCreate($id, $request, $changeRequest_old, $type = 'create'): bool
    {
        $log = new self();
        // $user_id = $request->user_id ? $request->user_id : \Auth::user()->id;

        if ($request instanceof \Illuminate\Support\Collection) {
            $user_id = $request->get('user_id', Auth::id());
        } elseif (is_array($request)) {
            $user_id = $request['user_id'] ?? Auth::id();
        } elseif ($request instanceof \Illuminate\Http\Request) {
            $user_id = $request->input('user_id', Auth::id());
        } else {
            $user_id = Auth::id();
        }

        $user = User::find($user_id);

        /**
         * @var Change_request $change_request
         */
        $change_request = $changeRequest_old;

        if ($type === 'create') {

            // Check if the CR workflow is promo
            if ((int) data_get($request, 'workflow_type_id') === 9) {
                $this->log_prefix = 'Promo';
            }

            $this->createLog($log, $id, $user->id, "$this->log_prefix Created By '$user->user_name'");

            $new_status_id = data_get($request, 'new_status_id');

            $log_message = $this->prepareCRStatusLogMessage($new_status_id, $user, 'create');

            $this->createLog($log, $id, $user->id, $log_message);

            return true;
        }

        // Check if the CR workflow is promo
        if ((int) $change_request?->workflow_type_id === 9) {
            $this->log_prefix = 'Promo';
        }

        if ($type === 'shifting') {
            $this->createLog($log, $id, $user->id, "$this->log_prefix shifted by admin : '$user->user_name'");

            return true;
        }

        $fields = [
            // 'analysis_feedback' => 'Analysis FeedBack',
            // 'comment' => 'Comment',
            'priority_id' => ['model' => Priority::class, 'field' => 'name', 'message' => "$this->log_prefix Priority Changed To"],
            // 'technical_feedback' => 'Technical Feedback Is',
            'unit_id' => ['model' => Unit::class, 'field' => 'name', 'message' => "$this->log_prefix Assigned To Unit"],
            // 'creator_mobile_number' => 'Creator Mobile Changed To',
            // 'title' => 'Subject Changed To',
            'application_id' => ['model' => Application::class, 'field' => 'name', 'message' => "$this->log_prefix Title Changed To"],
            // 'description' => 'CR Description To',
            'category_id' => ['model' => Category::class, 'field' => 'name', 'message' => "$this->log_prefix Category Changed To"],
            'division_manager_id' => ['model' => DivisionManagers::class, 'field' => 'name', 'message' => 'Division Managers To'],
            'need_down_time' => ['model' => NeedDownTime::class, 'field' => 'name', 'message' => "$this->log_prefix Need down time Changed To"],
            'rejection_reason_id' => ['model' => Rejection_reason::class, 'field' => 'name', 'message' => "$this->log_prefix rejection Reason Changed To"],
            'deployment_impact' => ['model' => DeploymentImpact::class, 'field' => 'name', 'message' => "$this->log_prefix Deployment Impact Changed To"],
        ];

        if ($user->isSystemAdmin()) {
            $user->user_name = 'System';
        }

        // Excluded to be handled in separate function
        $excludeNames = ['new_status_id', 'testing_estimation', 'design_estimation', 'dev_estimation', 'postpone', 'need_ux_ui', 'active'];

        $cr_current_status = $change_request->getCurrentStatus();

        $cr_current_status_id = $cr_current_status?->new_status_id;

        // If there is a new status get the old status for correct log message
        if ($request->get('new_status_id')) {
            $cr_current_status_id = $cr_current_status?->old_status_id;
        }

        $filtered_request = array_filter($request->except(['_token', '_method', 'cr']), static function ($value) {
            if (is_array($value)) {
                return count(array_filter($value)) > 0;
            }

            return $value !== null && $value !== '';
        });

        $customFields = CustomField::query()
            ->whereIn('name', array_keys($filtered_request))
            ->whereNotIn('name', $excludeNames)
            ->withLogMessageForStatus($cr_current_status_id)
            ->get();

        $cf_default_log_message = ":cf_label Changed To ':cf_value' by :user_name";

        $customFieldMap = $customFields->mapWithKeys(function ($cf) use ($request, $cf_default_log_message, $user, $change_request) {

            // Fallback message if label is null
            $label = $cf->label ?: Str::of($cf->name)->remove('_id')->replace('_', ' ')->title();

            // CF Log message
            $cf_log_message = $cf->customFieldStatus?->log_message ?? $cf->log_message ?? $cf_default_log_message;

            $base = [];

            // Prepare old values for comparison
            if (in_array($cf->type, ['textArea', 'file'], true)) {
                $base['old_value'] = null;
            } else {
                $latest_value = $change_request->changeRequestCustomFields->where('custom_field_name', $cf->name)->last()?->custom_field_value;

                $base['old_value'] = json_decode($latest_value, true);
            }

            if (in_array($cf->type, ['multiselect', 'select'], true)) {
                $data = $cf->getSpecificCustomFieldValues((array) $request->{$cf->name});

                $value = $data?->implode(', ');
            }

            if ($cf->name === 'testable') {
                $value = $request->get('testable') === '1' ? 'Testable' : 'Non Testable';
            }

            if ($cf->type === 'file') {
                $files_name = [];
                $attachments = $request->file($cf->name, []);

                foreach ($attachments as $attachment) {
                    $files_name[] = $attachment->getClientOriginalName();
                }

                $value = implode(', ', $files_name);
            }

            if ($cf->name === 'depend_on') {
                // For depend_on, use the CR Numbers directly
                $value = is_array($request->depend_on) ? implode(', ', array_filter($request->depend_on)) : $request->depend_on;
            }

            $base['message'] = trans($cf_log_message, [
                'prefix' => $this->log_prefix,
                'cf_label' => $label,
                'cf_value' => $value ?? $request->{$cf->name},
                'user_name' => $user->user_name,
            ]);

            $base['already_has_message'] = true;

            return [$cf->name => $base];
        })->toArray();

        $all_logs = [];

        // append without overriding existing keys in $fields
        $fields += $customFieldMap;
        foreach ($fields as $field => $info) {
            if (isset($request->{$field})) {
                if ($field === 'kpi') {
                    $oldValue = $change_request->kpis->first()->id ?? null;
                    $newValue = $request->kpi;
                } elseif ($field === 'depend_on') {
                    $oldValue = $change_request->dependencies
                        ->where('pivot.status', '0')
                        ->pluck('cr_no')
                        ->toArray();

                    $newValue = (array) $request->{$field};
                } elseif (is_array($info) && array_key_exists('old_value', $info)) {
                    $oldValue = $info['old_value'];
                    $newValue = $request->{$field};
                } else {
                    $oldValue = $change_request->{$field} ?? null;
                    $newValue = $request->{$field};
                }

                if (($oldValue != $newValue) && is_array($info)) {
                    if (isset($info['model'])) {
                        $modelName = $info['model'];
                        $fieldName = $info['field'];
                        $valueName = $modelName::find($newValue)?->$fieldName;
                        $message = $info['message'] . " '$valueName' By '$user->user_name'";

                        $all_logs[] = $message;
                    } elseif (array_key_exists('already_has_message', $info)) {
                        $all_logs[] = $info['message'];
                    }
                }
            }
        }

        // Store all logs
        $this->createMultipleLogs($change_request->id, $user->id, $all_logs);

        // Boolean Toggles
        $this->logToggle($log, $id, $user->id, $request, $change_request, 'postpone', 'PostPone changed To');
        $this->logToggle($log, $id, $user->id, $request, $change_request, 'need_ux_ui', 'Need UI UX changed To');

        // User Assignments
        $assignments = [
            'assign_to' => "$this->log_prefix assigned manually to",
        ];

        $assignment_logs = [];

        foreach ($assignments as $field => $label) {
            if (isset($request->$field)) {
                // TODO: Take this query out of the foreach
                $assignedUser = User::find($request->$field);
                if ($assignedUser) {
                    $assignment_logs[] = "$label '{$assignedUser->user_name}' by {$user->user_name}";
                }
            }
        }

        $this->createMultipleLogs($id, $user->id, $assignment_logs);

        // Estimations without assignments

        $this->logEstimateWithoutAssignee($log, $id, $user, $request, 'design_duration', 'design_duration', 'Design');
        $this->logEstimateWithoutAssignee($log, $id, $user, $request, 'develop_duration', 'developer_id', 'Dev');
        $this->logEstimateWithoutAssignee($log, $id, $user, $request, 'test_duration', 'tester_id', 'Testing');

        // Durations with times
        $this->logDurationWithTimes($log, $id, $user, $request, 'design_duration', 'start_design_time', 'end_design_time');
        $this->logDurationWithTimes($log, $id, $user, $request, 'develop_duration', 'start_develop_time', 'end_develop_time');
        $this->logDurationWithTimes($log, $id, $user, $request, 'test_duration', 'start_test_time', 'end_test_time');

        $status_logs = [];

        // Status change
        if (isset($request->new_status_id)) {
            $workflow = NewWorkFlow::find($request->new_status_id);

            $status_title = null;
            if ($workflow && ! empty($workflow->to_status_label)) {
                $status_title = $workflow->to_status_label;
            }

            if ($status_title && $request->missing('hold') && $request->missing('is_final_confirmation')) {
                // Dependency Release Log (when the depend cr reach to the status delivered or reject)
                if ($request->released_from_hold) {
                    $newStatusesIds = NewWorkFlowStatuses::where('new_workflow_id', $request->new_status_id)->pluck('to_status_id')->toArray();
                    $newStatusesNames = Status::whereIn('id', $newStatusesIds)->pluck('status_name')->toArray();
                    $actualStatuses = implode(', ', $newStatusesNames);

                    $status_logs[] = "$this->log_prefix status has been released by {$user->user_name} and the current status is $actualStatuses";
                }
                // Dependency Hold Log
                elseif ($change_request->fresh()->is_dependency_hold) {
                    $newStatusesIds = NewWorkFlowStatuses::where('new_workflow_id', $request->new_status_id)->pluck('to_status_id')->toArray();
                    $newStatusesNames = Status::whereIn('id', $newStatusesIds)->pluck('status_name')->toArray();
                    $actualStatuses = implode(', ', $newStatusesNames);

                    $blockingCrs = \App\Models\CrDependency::where('cr_id', $id)
                        ->active()
                        ->with('dependsOnCr:id,cr_no')
                        ->get()
                        ->pluck('dependsOnCr.cr_no')
                        ->filter()
                        ->implode(', ');

                    $status_logs[] = "$this->log_prefix Status changed to '$actualStatuses' by {$user->user_name} (Pending Dependency (CR#$blockingCrs))";
                }
                // Normal Status Log
                else {
                    $log_message_template = $request->get('cron_status_log_message', $workflow->log_message);
                    $log_message = $this->prepareCRStatusLogMessage($request->new_status_id, $user, custom_log_message_template: $log_message_template);

                    $status_logs[] = $log_message;
                }
            } else {
                $log_message_template = $request->get('cron_status_log_message', $workflow->log_message);
                $log_message = $this->prepareCRStatusLogMessage($request->new_status_id, $user, custom_log_message_template: $log_message_template);

                if ($request->has('is_final_confirmation')) {
                    $log_message = "$log_message from Administration";
                }

                $status_logs[] = $log_message;
            }
        }

        if ($request->hold === 1) {
            $status_logs = array_merge($status_logs, $this->prepareHoldLogs($user->user_name));
        } elseif ($request->hold === 0) {
            $status_logs[] = "$this->log_prefix unheld by '$user->user_name'";
        }

        $this->createMultipleLogs($id, $user->id, $status_logs);

        return true;
    }

    private function createLog($logRepo, $crId, $userId, $message): void
    {
        $this->create([
            'cr_id' => $crId,
            'user_id' => $userId,
            'log_text' => $message,
        ]);
    }

    private function logToggle($logRepo, $crId, $userId, $request, $old, $field, $messagePrefix): void
    {
        if (isset($request->$field) && $request->$field != $old->$field) {
            $status = $request->$field == 1 ? 'Active' : 'InActive';
            $this->createLog($logRepo, $crId, $userId, "$messagePrefix $status BY " . Auth::user()->user_name);
        }
    }

    private function logEstimateWithoutAssignee($logRepo, $crId, $user, $request, $durationField, $assigneeField, $label): void
    {
        if (isset($request->$durationField) && empty($request->$assigneeField)) {
            $log_message = "$this->log_prefix $label Estimated by {$user->user_name}";

            if (! $this->logExists($log_message, $crId)) {
                $this->createLog($logRepo, $crId, $user->id, $log_message);
            }
        }
    }

    private function logDurationWithTimes($logRepo, $crId, $user, $request, $durationField, $startField, $endField): void
    {
        if (isset($request->$durationField)) {
            $cleaned_field = Str::of($durationField)->remove('_id')->replace('_', ' ')->title();
            $log_message = "$this->log_prefix $cleaned_field manually set to '{$request->$durationField} H' by {$user->user_name}";

            if (! $this->logExists($log_message, $crId)) {
                $this->createLog($logRepo, $crId, $user->id, $log_message);
            }
        }

        if (isset($request->$startField) && isset($request->$endField)) {
            $NewStartField = match ($startField) {
                'start_design_time' => 'design_start_time',
                'start_develop_time' => 'develop_start_time',
                'start_test_time' => 'testing_start_time',
                default => $startField
            };

            $startLabel = Str::of($NewStartField)->replace('_', ' ')->title();
            //            $endLabel = Str::of($endField)->replace('_', ' ')->title();

            $log_message = "$this->log_prefix $startLabel set to '{$request->$startField}' and end time set to '{$request->$endField}' by {$user->user_name}";

            if (! $this->logExists($log_message, $crId)) {
                $this->createLog($logRepo, $crId, $user->id, $log_message);
            }

        }
    }

    private function logExists(string $log_message, string $crId): bool
    {
        return Log::where('cr_id', $crId)->where('log_text', $log_message)->exists();
    }

    private function prepareCRStatusLogMessage(int $status_id, User $user, ?string $stage = null, ?string $custom_log_message_template = null): string
    {
        $default_status_log_message = "$this->log_prefix Status changed to ':status_name' By ':user_name'";

        if ($stage === 'create') {
            $status = Status::findOrFail($status_id);

            $status_name = $status?->status_name;
            $log_message = $custom_log_message_template ?? $status->log_message ?? $default_status_log_message;
        } else {
            $newStatusesIds = NewWorkFlowStatuses::where('new_workflow_id', $status_id)->pluck('to_status_id')->toArray();
            $statuses = Status::whereIn('id', $newStatusesIds)->toBase()->get(['status_name', 'log_message']);

            $status_name = $statuses?->pluck('status_name')->unique()->implode(', ');
            $log_message = $custom_log_message_template ?? $statuses->whereNotNull('log_message')->first()->log_message ?? $default_status_log_message;
        }

        return trans($log_message, [
            'prefix' => $this->log_prefix,
            'status_name' => $status_name,
            'user_name' => $user->user_name,
        ]);
    }

    private function createMultipleLogs(int $crId, int $userId, array $logs): void
    {
        if (count($logs) === 0) {
            return;
        }

        $formated_logs = [];
        $now = now();

        foreach ($logs as $log_message) {
            $formated_logs[] = [
                'cr_id' => $crId,
                'user_id' => $userId,
                'log_text' => $log_message,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Log::insert($formated_logs);
    }

    private function prepareHoldLogs(string $user_name): array
    {
        $logs = [];

        $logs[] = "$this->log_prefix Held by '$user_name'";

        $resume_date = request()->date('resuming_date')?->format('d/m/Y h:i a');
        if ($resume_date) {
            $logs[] = "$this->log_prefix Resume Date set to be '$resume_date' by '$user_name'";
        }

        $hold_reason_id = request('hold_reason_id');
        if ($hold_reason_id && $hold_reason = HoldReason::where('id', $hold_reason_id)->value('name')) {
            $logs[] = "$this->log_prefix Held with reason '$hold_reason' by '$user_name'";
        }

        return $logs;
    }
}
