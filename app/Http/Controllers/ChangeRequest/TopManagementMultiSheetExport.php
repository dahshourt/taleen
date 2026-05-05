<?php

namespace App\Http\Controllers\ChangeRequest;

use App\Models\Change_request;
use App\Models\WorkFlowType;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class TopManagementMultiSheetExport implements WithMultipleSheets
{
    protected $workflows;
    protected $topManagementCrsByWorkflow;

    public function __construct()
    {
        // Get all workflow types that have CRs with top_management = 1
        $this->workflows = WorkFlowType::whereHas('changeRequests', function ($query) {
            $query->where('top_management', '1');
        })
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->orderBy('id')
            ->get();

        // If no workflows have top management CRs, get all active workflow types
        if ($this->workflows->count() === 0) {
            $this->workflows = WorkFlowType::active()
                ->orderBy('id')
                ->get();
        }

        // Get top management CRs grouped by workflow type
        $this->topManagementCrsByWorkflow = [];
        foreach ($this->workflows as $workflow) {
            $this->topManagementCrsByWorkflow[$workflow->id] = Change_request::where('top_management', '1')
                ->where('workflow_type_id', $workflow->id)
                ->with(['member', 'application', 'currentRequestStatuses.status'])
                ->orderBy('cr_no', 'desc')
                ->get();
        }
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->workflows as $workflow) {
            $crs = $this->topManagementCrsByWorkflow[$workflow->id] ?? collect();
            $sheets[] = new TopManagementSheetExport($workflow->name, $crs);
        }

        return $sheets;
    }
}

class TopManagementSheetExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected $workflowName;
    protected $crs;

    public function __construct($workflowName, $crs)
    {
        $this->workflowName = $workflowName;
        $this->crs = $crs;
    }

    public function title(): string
    {
        // Sanitize sheet name to be Excel-compatible (max 31 characters, no special chars)
        $sheetName = $this->workflowName;
        $sheetName = preg_replace('/[\\:*?"\/<>|]/', '', $sheetName); // Remove invalid characters
        return substr($sheetName, 0, 31); // Limit to 31 characters
    }

    public function collection()
    {
        return $this->crs;
    }

    public function headings(): array
    {
        return [
            'CR Number',
            'Title',
            'Status',
            'CR Manager',
            'Target System',
            'CR Type',
            'Top Management',
            'On Behalf',
            'On Hold',
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
        ];
    }

    public function map($cr): array
    {
        $current_status = $cr->currentRequestStatuses;
        $status_name = ($current_status && $current_status->status) ? $current_status->status->name : 'N/A';

        return [
            $cr->cr_no,
            $cr->title,
            $status_name,
            $cr->member ? $cr->member->user_name : 'N/A',
            $cr->application ? $cr->application->name : 'N/A',
            $cr->ticket_type,
            $cr->top_management == '1' ? 'YES' : 'N/A',
            $cr->on_behalf_status,
            $cr->hold == '1' ? 'YES' : 'N/A',
            $cr->design_duration,
            $cr->start_design_time,
            $cr->end_design_time,
            $cr->develop_duration,
            $cr->start_develop_time,
            $cr->end_develop_time,
            $cr->test_duration,
            $cr->start_test_time,
            $cr->end_test_time,
            $cr->CR_duration,
            $cr->start_CR_time,
            $cr->end_CR_time,
        ];
    }
}
