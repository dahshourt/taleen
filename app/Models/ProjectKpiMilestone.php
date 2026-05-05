<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectKpiMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_kpi_quarter_id',
        'milestone',
        'status',
        'created_by',
        'updated_by',
    ];

    const STATUS = [
        'Not Started',
        'In Progress',
        'Delivered',
        'On-Hold',
        'Canceled',
    ];

    /**
     * Get the quarter that owns the milestone.
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(ProjectKpiQuarter::class, 'project_kpi_quarter_id');
    }
}
