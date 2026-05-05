<?php

namespace App\Exports;

use App\Models\NewWorkFlow;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WorkflowsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function collection()
    {
        return NewWorkFlow::where('type_id', '!=', 7)
            ->with([
                'type:id,name',
                'previous_status:id,status_name',
                'from_status:id,status_name',
                'workflowstatus.to_status:id,status_name',
            ])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Type',
            'Previous Status',
            'From Status',
            'To Status',
            'To Status Label',
            'Default Status',
            'Status',
        ];
    }

    public function map($workflow): array
    {
        $typeName = $workflow->type ? $workflow->type->name : '';
        $previousStatusName = $workflow->previous_status ? $workflow->previous_status->status_name : '';
        $fromStatusName = $workflow->from_status ? $workflow->from_status->status_name : '';

        $toStatusNames = '';
        if ($workflow->workflowstatus) {
            $statusNames = [];
            foreach ($workflow->workflowstatus as $ws) {
                if ($ws->to_status) {
                    $statusNames[] = $ws->to_status->status_name;
                }
            }
            $toStatusNames = implode(', ', $statusNames);
        }

        $defaultStatus = 'No';
        if ($workflow->workflowstatus) {
            foreach ($workflow->workflowstatus as $ws) {
                if ($ws->default_to_status == '1' || $ws->default_to_status == 1) {
                    $defaultStatus = 'Yes';
                    break;
                }
            }
        }

        $activeStatus = $workflow->active ? 'Active' : 'Inactive';

        return [
            $workflow->id,
            $typeName,
            $previousStatusName,
            $fromStatusName,
            $toStatusNames,
            $workflow->to_status_label ?? '',
            $defaultStatus,
            $activeStatus,
        ];
    }
}
