<?php 

namespace App\Http\Controllers\Report;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ActualVsPlannedReportExport implements FromCollection, WithHeadings
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
            'Applications',
            'Title',
            'CR Type',
            'On Behalf',
            'On Hold',
            'Top Management',
            'Ticket Type',
            'Design Estimation Planned Start',
            'Design Estimation Planned End',
            'Design In Progress Actual Start',
            'Design In Progress Actual End',
            'Pending Design Duration',
            'Team Member Name',
            'Technical Estimation Planned Start',
            'Technical Estimation Planned End',
            'Technical Implementation Actual Start',
            'Technical Implementation Actual End',
            'Pending Implementation Duration',
            'Technical Team',
            'Pending Implementation Assigned Member',
            'Testing Estimation Planned Start',
            'Testing Estimation Planned End',
            'Pending Testing Actual Start',
            'Pending Testing Actual End',
            'Testing Team Member',
            'Expected Delivery Date',
            'Requester Name',
            'Division Manager',
            'Requester Division',
            'Requester Sector',
        ];
    }
}



?>