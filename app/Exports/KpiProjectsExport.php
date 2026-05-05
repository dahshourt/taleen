<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;

class KpiProjectsExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    protected $kpiId;
    protected $mergeCells = [];

    public function __construct(int $kpiId)
    {
        $this->kpiId = $kpiId;
    }

    public function collection()
    {
        $projects = Project::whereHas('kpis', function ($q) {
                $q->where('kpi_id', $this->kpiId);
            })
            ->with(['quarters' => function ($query) {
                $query->whereNull('deleted_at')->orderBy('quarter');
            }, 'quarters.milestones' => function ($query) {
                $query->whereNull('deleted_at');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $rows = collect();
        $currentRow = 2;
        $iterationNumber = 1;

        foreach ($projects as $project) {
            $projectStartRow = $currentRow;
            $totalProjectRows = 0;

            foreach ($project->quarters as $quarter) {
                $totalProjectRows += max(1, $quarter->milestones->count());
            }

            if ($totalProjectRows === 0) {
                $totalProjectRows = 1;
            }

            if ($totalProjectRows > 1) {
                $this->mergeCells[] = ['range' => "A{$projectStartRow}:A" . ($projectStartRow + $totalProjectRows - 1), 'value' => $iterationNumber];
                $this->mergeCells[] = ['range' => "B{$projectStartRow}:B" . ($projectStartRow + $totalProjectRows - 1), 'value' => $project->name];
                $this->mergeCells[] = ['range' => "C{$projectStartRow}:C" . ($projectStartRow + $totalProjectRows - 1), 'value' => $project->status];
                $this->mergeCells[] = ['range' => "D{$projectStartRow}:D" . ($projectStartRow + $totalProjectRows - 1), 'value' => $project->project_manager_name];
            }

            foreach ($project->quarters as $quarter) {
                $quarterStartRow = $currentRow;
                $quarterRows = max(1, $quarter->milestones->count());

                if ($quarterRows > 1) {
                    $this->mergeCells[] = ['range' => "E{$quarterStartRow}:E" . ($quarterStartRow + $quarterRows - 1), 'value' => $quarter->quarter];
                }

                if ($quarter->milestones->count() > 0) {
                    foreach ($quarter->milestones as $milestone) {
                        $rows->push([
                            'iteration' => ($currentRow === $projectStartRow) ? $iterationNumber : '',
                            'project_name' => ($currentRow === $projectStartRow) ? $project->name : '',
                            'project_status' => ($currentRow === $projectStartRow) ? $project->status : '',
                            'project_manager' => ($currentRow === $projectStartRow) ? $project->project_manager_name : '',
                            'quarter' => ($currentRow === $quarterStartRow) ? $quarter->quarter : '',
                            'milestone' => $milestone->milestone,
                            'milestone_status' => $milestone->status,
                        ]);
                        $currentRow++;
                    }
                } else {
                    $rows->push([
                        'iteration' => ($currentRow === $projectStartRow) ? $iterationNumber : '',
                        'project_name' => ($currentRow === $projectStartRow) ? $project->name : '',
                        'project_status' => ($currentRow === $projectStartRow) ? $project->status : '',
                        'project_manager' => ($currentRow === $projectStartRow) ? $project->project_manager_name : '',
                        'quarter' => $quarter->quarter,
                        'milestone' => '',
                        'milestone_status' => '',
                    ]);
                    $currentRow++;
                }
            }

            if ($project->quarters->count() === 0) {
                $rows->push([
                    'iteration' => $iterationNumber,
                    'project_name' => $project->name,
                    'project_status' => $project->status,
                    'project_manager' => $project->project_manager_name,
                    'quarter' => '',
                    'milestone' => '',
                    'milestone_status' => '',
                ]);
                $currentRow++;
            }

            $iterationNumber++;
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            '#',
            'Project Name',
            'Project Status',
            'Project Manager',
            'Quarter',
            'Milestone',
            'Milestone Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                foreach ($this->mergeCells as $mergeInfo) {
                    $sheet->mergeCells($mergeInfo['range']);
                    $firstCell = explode(':', $mergeInfo['range'])[0];
                    $sheet->setCellValue($firstCell, $mergeInfo['value']);
                }

                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setAutoSize(true);
                $sheet->getColumnDimension('D')->setAutoSize(true);
                $sheet->getColumnDimension('E')->setAutoSize(true);
                $sheet->getColumnDimension('G')->setAutoSize(true);

                $sheet->getColumnDimension('F')->setWidth(50);
                $sheet->getStyle('F2:F' . $highestRow)->getAlignment()->setWrapText(true);

                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setVertical(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                );

                $sheet->getStyle('A2:E' . $highestRow)->getAlignment()->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );

                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}


