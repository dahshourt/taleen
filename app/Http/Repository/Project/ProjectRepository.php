<?php

namespace App\Http\Repository\Project;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectRepository
{
    public function getAll(): Collection
    {
        return Project::with(['quarters' => function ($query) {
            $query->whereNull('deleted_at');
        }, 'quarters.milestones' => function ($query) {
            $query->whereNull('deleted_at');
        }])
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Return all projects (lightweight) for dropdowns/selects.
     */
    public function listAll(): Collection
    {
        return Project::orderBy('id')->get(['id', 'name', 'status']);
    }

    /**
     * Return projects that are not linked to any KPI (kpi_projects table).
     */
    public function listUnlinked(): Collection
    {
        return Project::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('kpi_projects')
                ->whereColumn('kpi_projects.project_id', 'projects.id');
        })
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'project_manager_name', 'created_at']);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(array $data, int $id): bool
    {
        return Project::where('id', $id)->update($data);
    }

    public function find(int $id): ?Project
    {
        return Project::with(['quarters' => function ($query) {
            $query->whereNull('deleted_at');
        }, 'quarters.milestones' => function ($query) {
            $query->whereNull('deleted_at');
        }])->find($id);
    }

    public function delete(int $id): bool
    {
        $project = $this->find($id);

        if (! $project) {
            return false;
        }

        return $project->delete();
    }
}
