<?php

namespace App\Exports;

use App\Models\Kpi;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KPIsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Kpi::with(['creator:id,name', 'type:id,name'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            '#',
            'KPI Name',
            'Priority',
            'Status',
            'Quarter',
            'Type',
            'Classification',
            'Created By',
            'Created At',
        ];
    }

    /**
     * @param  \App\Models\Kpi  $kpi
     */
    public function map($kpi): array
    {
        return [
            $kpi->id,
            $kpi->name,
            $kpi->priority ?? 'N/A',
            $kpi->status ?? 'N/A',
            $kpi->target_launch_quarter ?? 'N/A',
            $kpi->type->name ?? 'N/A',
            $kpi->classification ?? 'N/A',
            $kpi->creator->name ?? 'N/A',
            $kpi->created_at ? $kpi->created_at->format('Y-m-d H:i:s') : 'N/A',
        ];
    }
}
