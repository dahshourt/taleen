<?php

namespace App\Exports;

use App\Models\Change_request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KpiChangeRequestsExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    protected int $kpiId;

    public function __construct(int $kpiId)
    {
        $this->kpiId = $kpiId;
    }

    /**
     * Build collection of related Change Requests for the given KPI.
     */
    public function collection()
    {
        $crs = Change_request::with(['workflowType', 'currentStatusRel'])
            ->whereHas('kpis', function ($q) {
                $q->where('kpi_id', $this->kpiId);
            })
            ->orderByDesc('created_at')
            ->get();

        return $crs->map(function ($cr, $index) {
            $statusName = optional(optional($cr->currentStatusRel)->status)->status_name;
            $workflowName = $cr->workflowType->name ?? '';

            return [
                'No'        => $index + 1,
                'CR Number' => $cr->cr_no,
                'Title'     => $cr->title,
                'Status'    => $statusName,
                'Workflow'  => $workflowName,
                'Requester' => $cr->requester_name ?? '',
                'Created At'=> optional($cr->created_at)->format('Y-m-d H:i'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            '#',
            'CR Number',
            'Title',
            'Status',
            'Workflow',
            'Requester',
            'Created At',
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
                    'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
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

                // Apply borders to all cells
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Auto-size columns
                foreach (range('A', $highestColumn) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
