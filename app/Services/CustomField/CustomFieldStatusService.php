<?php

namespace App\Services\CustomField;

use App\Http\Repository\CustomField\CustomFieldStatusRepository;
use App\Models\Status;
use Illuminate\Support\Collection;

class CustomFieldStatusService
{
    public function __construct(
        private CustomFieldStatusRepository $repository
    ) {}

    public function getByCustomFieldId(int $customFieldId): Collection
    {
        return $this->repository->getByCustomFieldId($customFieldId);
    }

    public function getAllActiveStatuses(): Collection
    {
        return Status::active()->get();
    }

    public function syncCustomFieldStatuses(int $customFieldId, array $statuses): void
    {
        $this->repository->syncCustomFieldStatuses($customFieldId, $statuses);
    }
}
