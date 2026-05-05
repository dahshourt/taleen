<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class KpiReportExport implements FromCollection, WithHeadings
{
    protected $rows;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return [
            'CR No',
            'Category',
            'Current Status',
            'Requester Name',
            'On Behalf',
            'On Hold',
            'Top Management',
            'Ticket Type',
            'Targeted System',
            'Technical Team',
            'Design Status',
            'Testing Status',
            'Design Estimation',
            'Technical Estimation',
            'Testing Estimation',
            'Test in Progress',
            'UAT In Progress',
            'Sanity Check',
            'Healthy Check',
            'Count-Required Info',
            'Time -Required Info',
            'Count-Design Rework',
            'Rework Time-Design Rework',
            'Rework',
            'Time Rework',
            'Count TC-Rwork',
            'Time TC-Rwork',
            'Meet Delivery Date',
        ];
    }
}
