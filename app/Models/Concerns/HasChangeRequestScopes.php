<?php

namespace App\Models\Concerns;

use App\Services\StatusConfigService;
use Illuminate\Database\Eloquent\Builder;

trait HasChangeRequestScopes
{
    public function scopeNotInFinalState(Builder $query): Builder
    {
        return $query->whereHas('currentRequestStatuses', function ($query) {
            return $query->whereNotIn('new_status_id', [StatusConfigService::getStatusId('Reject'), StatusConfigService::getStatusId('Cancel'), config('change_request.parked_status_ids.promo_closure')]);
        });
    }

    public function scopeFilters(Builder $query): Builder
    {
        return $query
            ->when(request()->query('cr_no'), function (Builder $query, $value) {
                $query->where('cr_no', $value);
            })
            ->when(request()->query('title'), function (Builder $query, $value) {
                $query->where('title', 'like', "%{$value}%");
            })
            ->when(request()->query('application_id'), function (Builder $query, $value) {
                $query->whereIn('application_id', (array) $value);
            })
            ->when(request()->query('tester_id'), function (Builder $query, $value) {
                $query->whereIn('tester_id', (array) $value);
            })
            ->when(request()->query('developer_id'), function (Builder $query, $value) {
                $query->whereIn('developer_id', (array) $value);
            })
            ->when(request()->query('designer_id'), function (Builder $query, $value) {
                $query->whereIn('designer_id', (array) $value);
            })
            ->when(request()->query('category_id'), function (Builder $query, $value) {
                $query->whereIn('category_id', (array) $value);
            })
            ->when(request()->query('priority_id'), function (Builder $query, $value) {
                $query->whereIn('priority_id', (array) $value);
            })
            ->when(request()->query('unit_id'), function (Builder $query, $value) {
                $query->whereIn('unit_id', (array) $value);
            })
            ->when(request()->query('division_manager'), function (Builder $query, $value) {
                $query->where('division_manager', $value);
            })
            ->when(request()->query('workflow_type_id'), function (Builder $query, $value) {
                $query->whereIn('workflow_type_id', (array) $value);
            })
            ->when(request()->query('requester_name'), function (Builder $query, $value) {
                $query->where('requester_name', 'like', "%{$value}%");
            })
            ->when(request()->query('created_at_start'), function (Builder $query, $value) {
                $query->whereDate('created_at', '>=', $value);
            })->when(request()->query('created_at_end'), function (Builder $query, $value) {
                $query->whereDate('created_at', '<=', $value);
            })
            ->when(request()->query('updated_at_start'), function (Builder $query, $value) {
                $query->whereDate('updated_at', '>=', $value);
            })
            ->when(request()->query('updated_at_end'), function (Builder $query, $value) {
                $query->whereDate('updated_at', '<=', $value);
            })
            ->when(request()->query('new_status_id'), function (Builder $query, $value) {
                $query->whereHas('currentRequestStatuses', function (Builder $query) use ($value) {
                    $query->whereIn('new_status_id', (array) $value);
                });
            })
            ->when(request()->query('cr_type'), function (Builder $query, $value) {
                $query->whereHas('changeRequestCustomFields', function ($q) use ($value) {
                    $q->where('custom_field_name', 'cr_type')
                        ->whereIn('custom_field_value', (array) $value);
                });
            });
    }

    /**
     * Scope a query to only include active change requests.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include change requests by priority.
     */
    public function scopeByPriority(Builder $query, int $priorityId): Builder
    {
        return $query->where('priority_id', $priorityId);
    }

    /**
     * Scope a query to only include change requests by category.
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to only include change requests assigned to a user.
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('developer_id', $userId)
                ->orWhere('tester_id', $userId)
                ->orWhere('designer_id', $userId);
        });
    }

    /**
     * Scope a query to only include overdue change requests.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('release_delivery_date', '<', now())
            ->where('active', true);
    }
}
