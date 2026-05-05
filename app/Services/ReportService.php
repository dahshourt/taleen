<?php

namespace App\Services;

use App\Http\Repository\Report\SLAReport;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportService
{
    public function __construct(private SLAReport $report) {}

    public function getSLAReport(): LengthAwarePaginator
    {
        return $this->report->SLAPReportPaginated();
    }

    public function SLAPReportAll(): array
    {
        return $this->report->SLAPReportAll();
    }
}
