<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;

class ProjectsExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    protected $mergeCells = []; // Track cells to merge

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $projects = Project::with(['quarters' => function ($query) {
            $query->whereNull('deleted_at')->orderBy('quarter');
        }, 'quarters.milestones' => function ($query) {
            $query->whereNull('deleted_at');
        }])->orderBy('created_at', 'desc')->get();

        $rows = collect();
        $currentRow = 2; // Start from row 2 (after headers)
        $iterationNumber = 1; // Counter for iteration numbers

        foreach ($projects as $project) {
            $projectStartRow = $currentRow;
            $totalProjectRows = 0;

            // Calculate total rows for this project
            foreach ($project->quarters as $quarter) {
                $totalProjectRows += max(1, $quarter->milestones->count());
            }

            // If no quarters, at least 1 row
            if ($totalProjectRows === 0) {
                $totalProjectRows = 1;
            }

            // Track merge ranges for # column and project columns (A, B, C, D)
            if ($totalProjectRows > 1) {
                $this->mergeCells[] = ['range' => "A{$projectStartRow}:A" . ($projectStartRow + $totalProjectRows - 1), 'value' => $iterationNumber];
                $this->mergeCells[] = ['range' => "B{$projectStartRow}:B" . ($projectStartRow + $totalProjectRows - 1), 'value' => $project->name];
                $this->mergeCells[] = ['range' => "C{$projectStartRow}:C" . ($projectStartRow + $totalProjectRows - 1), 'value' => $project->status];
                $this->mergeCells[] = ['range' => "D{$projectStartRow}:D" . ($projectStartRow + $totalProjectRows - 1), 'value' => $project->project_manager_name];
            }

            // Process each quarter
            foreach ($project->quarters as $quarter) {
                $quarterStartRow = $currentRow;
                $quarterRows = max(1, $quarter->milestones->count());

                // Track merge range for quarter column (E)
                if ($quarterRows > 1) {
                    $this->mergeCells[] = ['range' => "E{$quarterStartRow}:E" . ($quarterStartRow + $quarterRows - 1), 'value' => $quarter->quarter];
                }

                // Add milestone rows
                if ($quarter->milestones->count() > 0) {
                    foreach ($quarter->milestones as $index => $milestone) {
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
                    // Quarter with no milestones
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

            // If project has no quarters
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

            $iterationNumber++; // Increment for next project
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
            // Style the header row
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

                // Perform cell merging
                foreach ($this->mergeCells as $mergeInfo) {
                    $sheet->mergeCells($mergeInfo['range']);
                    // Set the value in the merged cell
                    $firstCell = explode(':', $mergeInfo['range'])[0];
                    $sheet->setCellValue($firstCell, $mergeInfo['value']);
                }

                // Apply borders to all cells
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Auto-size columns A, B, C, D, E, G (all except milestone column F)
                $sheet->getColumnDimension('A')->setAutoSize(true); // #
                $sheet->getColumnDimension('B')->setAutoSize(true); // Project Name
                $sheet->getColumnDimension('C')->setAutoSize(true); // Project Status
                $sheet->getColumnDimension('D')->setAutoSize(true); // Project Manager
                $sheet->getColumnDimension('E')->setAutoSize(true); // Quarter
                $sheet->getColumnDimension('G')->setAutoSize(true); // Milestone Status

                // Set fixed width for milestone column (F) - 50 characters width
                $sheet->getColumnDimension('F')->setWidth(50);

                // Wrap text for milestone column
                $sheet->getStyle('F2:F' . $highestRow)->getAlignment()->setWrapText(true);

                // Center align all cells vertically and horizontally
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setVertical(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                );

                // Center align # column, project info columns (B, C, D) and quarter column (E) horizontally
                $sheet->getStyle('A2:E' . $highestRow)->getAlignment()->setHorizontal(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                );

                // Set row height for header
                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}

