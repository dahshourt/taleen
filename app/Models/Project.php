<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'project_manager_name',
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

    public function quarters(): HasMany
    {
        return $this->hasMany(ProjectKpiQuarter::class);
    }

    /**
     * KPIs associated with this project.
     */
    public function kpis(): BelongsToMany
    {
        return $this->belongsToMany(Kpi::class, 'kpi_projects', 'project_id', 'kpi_id');
    }
}

