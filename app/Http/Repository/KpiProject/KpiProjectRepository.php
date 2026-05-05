<?php

namespace App\Http\Repository\KpiProject;

use App\Models\Kpi;
use App\Models\KpiProject;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KpiProjectRepository
{
    /**
     * Check if a project is already attached to a KPI.
     */
    public function isAttached(int $kpiId, int $projectId): bool
    {
        return KpiProject::where('kpi_id', $kpiId)
            ->where('project_id', $projectId)
            ->exists();
    }

    /**
     * Attach a project to a KPI.
     */
    public function attach(int $kpiId, int $projectId): KpiProject
    {
        return KpiProject::create([
            'kpi_id' => $kpiId,
            'project_id' => $projectId,
        ]);
    }

    /**
     * Detach a project from a KPI.
     */
    public function detach(int $kpiId, int $projectId): bool
    {
        return KpiProject::where('kpi_id', $kpiId)
            ->where('project_id', $projectId)
            ->delete();
    }

    /**
     * Get all projects attached to a KPI with their quarters and milestones.
     */
    public function getKpiProjects(int $kpiId): Collection
    {
        // Use direct query to avoid relationship caching issues
        $projectIds = KpiProject::where('kpi_id', $kpiId)
            ->pluck('project_id')
            ->unique()
            ->values()
            ->toArray();
        
        if (empty($projectIds)) {
            return collect();
        }
        
        // Load projects with their relationships directly
        return Project::whereIn('id', $projectIds)
            ->with([
                'quarters' => function ($query) {
                    $query->whereNull('deleted_at');
                },
                'quarters.milestones' => function ($query) {
                    $query->whereNull('deleted_at');
                }
            ])
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * Get a KPI by ID.
     */
    public function getKpi(int $kpiId): ?Kpi
    {
        return Kpi::find($kpiId);
    }

    /**
     * Get a project by ID with quarters and milestones.
     */
    public function getProject(int $projectId): ?Project
    {
        return Project::with([
            'quarters' => function ($query) {
                $query->whereNull('deleted_at');
            },
            'quarters.milestones' => function ($query) {
                $query->whereNull('deleted_at');
            }
        ])->find($projectId);
    }
}

