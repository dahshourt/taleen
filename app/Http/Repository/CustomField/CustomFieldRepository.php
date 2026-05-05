<?php

namespace App\Http\Repository\CustomField;

use App\Models\CustomField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomFieldRepository
{
    /**
     * Get all custom fields with pagination
     *
     * @return LengthAwarePaginator|Collection
     */
    public function getAllCustomFields(bool $paginate = false)
    {
        $query = CustomField::orderBy('id', 'desc');

        return $paginate ? $query->paginate(15) : $query->get();
    }

    /**
     * Find custom field by ID
     */
    public function findCustomField(int $id): ?CustomField
    {
        return CustomField::find($id);
    }

    /**
     * Create new custom field
     */
    public function createCustomField(array $data): CustomField
    {
        return CustomField::create($data);
    }

    /**
     * Update custom field
     */
    public function updateCustomField(array $data, int $id): bool
    {
        $customField = $this->findCustomField($id);

        if (! $customField) {
            return false;
        }

        return $customField->update($data);
    }

    /**
     * Toggle custom field status
     */
    public function toggleCustomFieldStatus(int $id): bool
    {
        $customField = $this->findCustomField($id);

        if (! $customField) {
            return false;
        }

        $customField->active = $customField->active === '1' ? '0' : '1';

        return $customField->save();
    }

    /**
     * Delete custom field
     */
    public function deleteCustomField(int $id): bool
    {
        $customField = $this->findCustomField($id);

        if (! $customField) {
            return false;
        }

        return $customField->delete();
    }

    /**
     * Get active custom fields
     */
    public function getActiveCustomFields(): Collection
    {
        return CustomField::where('active', 1)->get();
    }

    /**
     * Search custom fields by name or label
     *
     * @return LengthAwarePaginator|Collection
     */
    public function searchCustomFields(string $search, bool $paginate = false)
    {
        $query = CustomField::where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('label', 'like', "%{$search}%")
                ->orWhere('type', 'like', "%{$search}%");
        })->orderBy('id', 'desc');

        return $paginate ? $query->paginate(15) : $query->get();
    }

    /**
     * Get custom fields by type
     */
    public function getCustomFieldsByType(string $type): Collection
    {
        return CustomField::where('type', $type)->get();
    }
}
