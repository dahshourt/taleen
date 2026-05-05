<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiSubInitiative extends Model
{
    protected $fillable = [
        'name',
        'initiative_id',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Scope a query to only include active sub-initiatives.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '1');
    }

    /**
     * Get the initiative that owns this sub-initiative.
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(KpiInitiative::class, 'initiative_id');
    }

    /**
     * Get the user who created this sub-initiative.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this sub-initiative.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

