<?php

namespace App\Exports;

use App\Services\ReportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportSLAReport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private ReportService $reportService) {}

    public function array(): array
    {
        $rows = $this->reportService->SLAPReportAll();

        return array_map(static function ($row) {
            return array_values((array) $row);
        }, $rows);
    }

    public function headings(): array
    {
        return [
            'CR No',
            'Category',
            'CR Date',
            'Requester',
            'Targeted System',
            'Technical Team',
            'Current Status',
            'Design Status',
            'Testable Status',
            'Business Estimation',
            'Testing Estimation',
            'Design Estimation',
            'Technical Estimation',
            'Design Document Approval',
            'Technical Test Case Approval',
            'Design Test Case Approval',
            'Business Test Case Approval',
            'Rollback',
            'Sanity Check',
            'Health Check',
        ];
    }
}
