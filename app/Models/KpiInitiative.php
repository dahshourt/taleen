<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiInitiative extends Model
{
    protected $fillable = [
        'name',
        'pillar_id',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Scope a query to only include active initiatives.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '1');
    }

    /**
     * Get the pillar that owns this initiative.
     */
    public function pillar(): BelongsTo
    {
        return $this->belongsTo(KpiPillar::class, 'pillar_id');
    }

    /**
     * Get the sub-initiatives for this initiative.
     */
    public function subInitiatives(): HasMany
    {
        return $this->hasMany(KpiSubInitiative::class, 'initiative_id');
    }

    /**
     * Get the user who created this initiative.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this initiative.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

