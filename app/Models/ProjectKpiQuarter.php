<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectKpiQuarter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'quarter',
        'created_by',
        'updated_by',
    ];

    const QUARTER = ['Q1', 'Q2', 'Q3', 'Q4'];

    /**
     * Get the project that owns the quarter.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the milestones for the quarter.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectKpiMilestone::class);
    }
}
