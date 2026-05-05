<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SlaReportExport implements FromCollection, WithHeadings
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
            'Business Validation',
            'Testing Estimation',
            'Design Estimation',
            'Technical Estimation',
            'Pending Design Document Approval QC',
            'Pending Design Document Approval DEV',
            'Technical Test Case Approval',
            'Design Test Case Approval',
            'Business Test Case Approval',
            'RollBack',
            'Sanity Check',
            'Health Check',
            'Design Estimation Comparison',
            'Technical Estimation Comparison',
            'Testing Estimation Comparison',
        ];
    }
}
