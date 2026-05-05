<?php

namespace App\Http\Controllers\Search;

use App\Models\Change_request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TableExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function collection()
    {
        return Change_request::with([
            'RequestStatuses' => function ($q) {
                $q->with('status');

                $selected_statuses = (array) request()->query('new_status_id', []);
                if (count($selected_statuses) > 0) {
                    $q->whereIn('new_status_id', $selected_statuses);
                }
            }
        ])->filters()->get();
    }

    public function headings(): array
    {
        return [
            'CR ID',
            'Title',
            'Category',
            'Release',
            'Current Status',
            'On Behalf',
            'Cr Type',
            'Top Management',
            'On Hold',
            'Requester',
            'Requester Email',
            'Design Duration',
            'Dev Duration',
            'Test Duration',
            'Testable Flag',
            'Creation Date',
            'Requesting Department',
            'Targeted System',
            'Assigned Technical Unit',
            'Last Action Date',

        ];
    }

    public function map($item): array
    {
        $statuses_names = $item->RequestStatuses->pluck('status.name');

        return [
            $item['cr_no'],
            $item['title'],
            $item['category']['name'] ?? '',
            $item['application']['name'] ?? '',
            $statuses_names->implode(', ') ?? '',
            $item['on_behalf'] ?? '',
            $item['cr_type_name'] ?? '',
            $item['top_management'] == 1 ? 'YES' : 'N/A',
            $item['hold'] == 1 ? 'YES' : 'N/A',
            $item['requester_name'] ?? '',
            $item['requester_email'] ?? '',
            $item['design_duration'] ?? '',
            $item['develop_duration'] ?? '',
            $item['test_duration'] ?? '',
            $item['testable'] == 1 ? 'YES' : 'NO',
            $item['created_at'] ?? '',
            is_object($item->requesterDepartment) ? ($item->requesterDepartment->name ?? '') : ($item->requesterDepartment ?? ''),
            $item['application']['name'] ?? '',
            $item['assigned_technical_units'] ?? 'NO',
            $item['updated_at'] ?? '',
        ];
    }
}
