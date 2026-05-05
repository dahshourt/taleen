<?php

namespace App\Http\Repository\CustomField;

use App\Models\CustomFieldStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomFieldStatusRepository
{
    public function getByCustomFieldId(int $customFieldId): Collection
    {
        return CustomFieldStatus::where('custom_field_id', $customFieldId)
            ->with('status:id,status_name')
            ->get();
    }

    public function create(array $data)
    {
        return CustomFieldStatus::create($data);
    }

    public function syncCustomFieldStatuses(int $customFieldId, array $statuses): void
    {
        DB::transaction(function () use ($customFieldId, $statuses) {
            // Get existing status IDs
            $existingStatusIds = CustomFieldStatus::where('custom_field_id', $customFieldId)
                ->pluck('status_id')
                ->toArray();

            // Get submitted status IDs
            $submittedStatusIds = array_filter(array_column($statuses, 'status_id'));

            // Delete statuses that are not in the submitted data
            $statusesToDelete = array_diff($existingStatusIds, $submittedStatusIds);
            if (! empty($statusesToDelete)) {
                CustomFieldStatus::where('custom_field_id', $customFieldId)
                    ->whereIn('status_id', $statusesToDelete)
                    ->delete();
            }

            // Prepare data for upsert
            $upsertData = [];
            $userId = auth()->id();
            foreach ($statuses as $statusData) {
                if (isset($statusData['status_id']) && $statusData['status_id']) {
                    $upsertData[] = [
                        'custom_field_id' => $customFieldId,
                        'status_id' => $statusData['status_id'],
                        'log_message' => $statusData['log_message'],
                        'user_id' => $userId,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                }
            }

            // Use upsert to insert or update records
            if (! empty($upsertData)) {
                CustomFieldStatus::upsert(
                    $upsertData,
                    ['custom_field_id', 'status_id'], // Unique columns
                    ['log_message', 'user_id'] // Columns to update on conflict
                );
            }
        });
    }
}
