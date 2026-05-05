<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kpi extends Model
{
    use DynamicColumns;

    const PRIORITY = ['Critical', 'High', 'Medium', 'Low'];

    const QUARTER = ['Q1', 'Q2', 'Q3', 'Q4'];

    const TYPE = ['Test Type 1', 'Test Type 2', 'Test Type 3', 'Test Type 4'];

    const CLASSIFICATION = ['CR', 'PM'];

    protected $table = 'kpis';

    protected $fillable = [
        'name',
        'priority',
        'pillar_id',
        'initiative_id',
        'sub_initiative_id',
        'bu',
        'sub_bu',
        'target_launch_quarter',
        'target_launch_year',
        'target_cr_count',
        'type_id',
        'classification',
        'kpi_brief',
        'status',
        'created_by',
        'requester_email',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(KpiComment::class)->latest();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(KpiLog::class)->oldest();
    }

    public function changeRequests(): BelongsToMany
    {
        return $this->belongsToMany(Change_request::class, 'kpi_change_request', 'kpi_id', 'cr_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(KpiType::class, 'type_id');
    }

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(KpiPillar::class, 'pillar_id');
    }

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(KpiInitiative::class, 'initiative_id');
    }

    public function subInitiative(): BelongsTo
    {
        return $this->belongsTo(KpiSubInitiative::class, 'sub_initiative_id');
    }

    public function kpiProjects(): HasMany
    {
        return $this->hasMany(KpiProject::class);
    }

    /**
     * Convenience relation to access related projects directly.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'kpi_projects', 'kpi_id', 'project_id')
            ->withPivot('created_at')
            ->withTimestamps();
    }
}
