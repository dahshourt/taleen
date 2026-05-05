<?php

namespace App\Exports;

use App\Models\Change_request;
use App\Models\WorkFlowType;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserCreatedCRsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(protected int $user_id, protected bool $current_user_is_just_a_viewer, protected string $workflow_type)
    {}

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Change_request::with([
            'release:id,name',
            'workflowType:id,name',
        ])->where('requester_id', $this->user_id);

        if ($this->workflow_type) {
            $workflow_type_id = WorkFlowType::where('name', $this->workflow_type)
                ->whereNotNull('parent_id')
                ->value('id');

            if ($workflow_type_id) {
                $query->where('workflow_type_id', $workflow_type_id);
            }
        }

        return $query->get();
    }

    public function headings(): array
    {
        $baseHeadings = [
            'CR No',
            'Title',
            'Status',
            'Workflow Type',
        ];

        // Add workflow-specific headings based on workflow type
        if ($this->workflow_type === 'In House') {
            return array_merge($baseHeadings, [
                'Design Duration',
                'Start Design Time',
                'End Design Time',
                'Development Duration',
                'Start Development Time',
                'End Development Time',
                'Test Duration',
                'Start Test Time',
                'End Test Time',
                'CR Duration',
                'Start CR Time',
                'End CR Time',
            ]);
        } elseif ($this->workflow_type === 'Vendor') {
            return array_merge($baseHeadings, [
                'Release',
                'Planned Start IOT Date',
                'Planned End IOT Date',
                'Planned Start E2E Date',
                'Planned End E2E Date',
                'Planned Start UAT Date',
                'Planned End UAT Date',
                'Planned Start Smoke Test Date',
                'Planned End Smoke Test Date',
                'Go Live Planned Date',
            ]);
        } elseif ($this->workflow_type === 'Promo') {
            return array_merge($baseHeadings, [
                'Created At',
            ]);
        }

        return $baseHeadings;
    }

    /**
     * @param  \App\Models\Change_request  $change_request
     */
    public function map($change_request): array
    {
        $cr_status = $change_request->getCurrentStatus()?->status;
        $statusName = $cr_status?->status_name;

        if ($this->current_user_is_just_a_viewer) {
            $high_level_status_name = $cr_status?->high_level?->name;
            $statusName = $high_level_status_name ?? $statusName;
        }

        $baseData = [
            $change_request->cr_no,
            $change_request->title,
            $statusName,
            $change_request->workflowType ? $change_request->workflowType->name : '',
        ];

        // Add workflow-specific data based on workflow type
        if ($this->workflow_type === 'In House') {
            return array_merge($baseData, [
                $change_request->design_duration,
                $change_request->start_design_time,
                $change_request->end_design_time,
                $change_request->develop_duration,
                $change_request->start_develop_time,
                $change_request->end_develop_time,
                $change_request->test_duration,
                $change_request->start_test_time,
                $change_request->end_test_time,
                $change_request->CR_duration,
                $change_request->start_CR_time,
                $change_request->end_CR_time,
            ]);
        } elseif ($this->workflow_type === 'Vendor') {
            return array_merge($baseData, [
                $change_request->release ? $change_request->release->name : 'No Release',
                $change_request->planned_start_iot_date,
                $change_request->planned_end_iot_date,
                $change_request->planned_start_e2e_date,
                $change_request->planned_end_e2e_date,
                $change_request->planned_start_uat_date,
                $change_request->planned_end_uat_date,
                $change_request->planned_start_smoke_test_date,
                $change_request->planned_end_smoke_test_date,
                $change_request->go_live_planned_date,
            ]);
        } elseif ($this->workflow_type === 'Promo') {
            return array_merge($baseData, [
                $change_request->created_at,
            ]);
        }

        return $baseData;
    }
}
