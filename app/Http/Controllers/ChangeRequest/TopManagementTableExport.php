<?php

namespace App\Http\Controllers\ChangeRequest;

use App\Models\Change_request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TopManagementTableExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        // Get all top management CRs with relationships
        return Change_request::where('top_management', '1')
            ->with(['member', 'application', 'currentRequestStatuses.status'])
            ->orderBy('cr_no', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'CR Number',
            'Title',
            'Status',
            'CR Manager',
            'Target System',
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

    /**
     * @param  \App\Models\Change_request  $cr
     */
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
