<?php

namespace App\Http\Repository\Releases;

use App\Contracts\Releases\ReleasesRepositoryInterface;
// declare Entities
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Repository\NewWorkflow\NewWorkflowRepository;
use App\Models\NewWorkFlow;
use App\Models\Release;
use App\Models\ReleaseLogs;
use App\Models\Status;
use App\Models\WorkFlowType;
use Auth;

class ReleaseRepository implements ReleasesRepositoryInterface
{
    public function getAll() {}

    public function find($id)
    {
        return Release::find($id);
    }

    public function create($request)
    {
        $releaseWorkflowTypeId = WorkFlowType::where('name', 'Release')->whereNotNull('parent_id')->value('id');
        $workflow = NewWorkFlow::where('type_id', $releaseWorkflowTypeId)->first();

        $release = Release::create([
            'name' => $request['name'],
            'vendor_id' => $request['vendor_id'],
            'priority_id' => $request['priority_id'],
            'target_system_id' => $request['target_system_id'] ?? null,
            'responsible_rtm_id' => $request['responsible_rtm_id'] ?? null,
            'creator_rtm_name' => $request['creator_rtm_name'],
            'rtm_email' => $request['rtm_email'],
            'release_description' => $request['release_description'] ?? null,
            'release_start_date' => $request['release_start_date'] ?? null,
            'go_live_planned_date' => $request['go_live_planned_date'] ?? null,
            'release_status' => $workflow->from_status_id ?? null,
        ]);

        // Set default "Planned" release status
        $releaseStatusService = new \App\Services\ReleaseStatusService();
        $releaseStatusService->setDefaultStatus($release);

        // Handle Technical Feedback
        if (!empty($request['technical_feedback'])) {
            \App\Models\ReleaseFeedback::create([
                'release_id' => $release->id,
                'feedback' => $request['technical_feedback'],
                'created_by' => auth()->id(),
            ]);
        }

        return $release;
    }

    public function list()
    {
        return Release::with(['vendor', 'status'])->get();
    }

    public function paginateAll($search = null)
    {
        return Release::with(['vendor', 'status'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10);
    }

    public function show($id)
    {
        return Release::where('id', $id)->first();
    }

    public function update($request, $id)
    {
        $old_release = $this->find($id);
        if (isset($request['status']) && ($old_release->release_status != $request['status'])) {
            $this->update_crs_of_release($id, $request['status']);
        }

        $updateData = [
            'name' => $request['name'] ?? $old_release->name,
            'release_status' => isset($request['status']) ? $request['status'] : $old_release->release_status,
            'release_start_date' => $request['release_start_date'] ?? $old_release->release_start_date,
            'go_live_planned_date' => $request['go_live_planned_date'] ?? $old_release->go_live_planned_date,
            'release_description' => array_key_exists('release_description', $request) ? $request['release_description'] : $old_release->release_description,
            'priority_id' => $request['priority_id'] ?? $old_release->priority_id,
            'responsible_rtm_id' => $request['responsible_rtm_id'] ?? $old_release->responsible_rtm_id,
        ];

        // Testing Schedule Dates (only update if present in request)
        $dateFields = [
            'atp_review_start_date', 'atp_review_end_date',
            'vendor_internal_test_start_date', 'vendor_internal_test_end_date',
            'iot_start_date', 'iot_end_date',
            'e2e_start_date', 'e2e_end_date',
            'uat_start_date', 'uat_end_date',
            'smoke_test_start_date', 'smoke_test_end_date',
        ];
        foreach ($dateFields as $dateField) {
            if (array_key_exists($dateField, $request)) {
                $updateData[$dateField] = $request[$dateField];
            }
        }

        Release::where('id', $id)->update($updateData);

        // Handle Technical Feedback (stored in separate table)
        if (!empty($request['technical_feedback'])) {
            \App\Models\ReleaseFeedback::create([
                'release_id' => $id,
                'feedback' => $request['technical_feedback'],
                'created_by' => Auth::id(),
            ]);
        }

        $this->StoreLog($id, $old_release, $request);

        return true;
    }

    public function StoreLog($id, $old_data, $request)
    {
        $user_name = 'admin';
        if (Auth::user()) {
            $user_name = Auth::user()->name ?? Auth::user()->user_name;
        }

        // Status Log
        if (isset($request['status']) && ($old_data->release_status != $request['status'])) {
            $oldStatusObj = \App\Models\ReleaseStatus::find($old_data->release_status);
            $newStatusObj = \App\Models\ReleaseStatus::find($request['status']);
            $old_status_name = $oldStatusObj->name ?? 'N/A';
            $new_status_name = $newStatusObj->name ?? 'N/A';
            $log_text = "Status changed from $old_status_name to $new_status_name by $user_name";
            $this->createLog($id, $log_text, Auth::id(), $request['status']);
        }

        // General Fields Log
        $fields = [
            'name' => 'Release Name',
            'release_start_date' => 'Release Start Date',
            'go_live_planned_date' => 'Go Live Planned Date',
            'release_description' => 'Release Description',
        ];

        foreach ($fields as $key => $label) {
            if (array_key_exists($key, $request) && ($old_data->$key != $request[$key])) {
                $oldVal = $old_data->$key ?? 'N/A';
                $newVal = $request[$key] ?: 'N/A';
                $log_text = "$label changed from '$oldVal' to '$newVal' by $user_name";
                $this->createLog($id, $log_text, Auth::id());
            }
        }

        // Priority change (lookup name)
        if (isset($request['priority_id']) && ($old_data->priority_id != $request['priority_id'])) {
            $oldPriority = \App\Models\Priority::find($old_data->priority_id);
            $newPriority = \App\Models\Priority::find($request['priority_id']);
            $log_text = "Priority changed from '" . ($oldPriority->name ?? 'N/A') . "' to '" . ($newPriority->name ?? 'N/A') . "' by $user_name";
            $this->createLog($id, $log_text, Auth::id());
        }

        // Responsible RTM change (lookup name)
        if (isset($request['responsible_rtm_id']) && ($old_data->responsible_rtm_id != $request['responsible_rtm_id'])) {
            $oldRtm = \App\Models\User::find($old_data->responsible_rtm_id);
            $newRtm = \App\Models\User::find($request['responsible_rtm_id']);
            $log_text = "Responsible RTM changed from '" . ($oldRtm->name ?? 'N/A') . "' to '" . ($newRtm->name ?? 'N/A') . "' by $user_name";
            $this->createLog($id, $log_text, Auth::id());
        }

        // Testing Schedule Date changes
        $dateLabels = [
            'atp_review_start_date' => 'ATP Review Start Date',
            'atp_review_end_date' => 'ATP Review End Date',
            'vendor_internal_test_start_date' => 'Vendor Internal Test Start Date',
            'vendor_internal_test_end_date' => 'Vendor Internal Test End Date',
            'iot_start_date' => 'IOT Start Date',
            'iot_end_date' => 'IOT End Date',
            'e2e_start_date' => 'E2E Start Date',
            'e2e_end_date' => 'E2E End Date',
            'uat_start_date' => 'UAT Start Date',
            'uat_end_date' => 'UAT End Date',
            'smoke_test_start_date' => 'Smoke Test Start Date',
            'smoke_test_end_date' => 'Smoke Test End Date',
        ];

        foreach ($dateLabels as $key => $label) {
            if (array_key_exists($key, $request) && ($old_data->$key != $request[$key])) {
                $oldVal = $old_data->$key ?? 'N/A';
                $newVal = $request[$key] ?: 'N/A';
                $log_text = "$label changed from '$oldVal' to '$newVal' by $user_name";
                $this->createLog($id, $log_text, Auth::id());
            }
        }

        // Technical Feedback added
        if (!empty($request['technical_feedback'])) {
            $log_text = "Technical Feedback added by $user_name";
            $this->createLog($id, $log_text, Auth::id());
        }

        return true;
    }

    private function createLog($release_id, $text, $user_id, $status_id = null) {
        $data = [
            'release_id' => $release_id,
            'user_id' => $user_id,
            'log_text' => $text,
        ];
        if($status_id) {
            $data['status_id'] = $status_id;
        }
        ReleaseLogs::create($data);
    }

    public function DisplayLogs($release_id)
    {
        $logs = ReleaseLogs::where('release_id', $release_id)->get();

        return $logs;
    }
    public function createAttachment($data)
    {
        return \App\Models\ReleaseAttachment::create($data);
    }

    public function findAttachment($id)
    {
        return \App\Models\ReleaseAttachment::find($id);
    }
}
