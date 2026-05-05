<?php

namespace App\Services\KpiProject;

use App\Http\Repository\KpiProject\KpiProjectRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class KpiProjectService
{
    public function __construct(private KpiProjectRepository $repository) {}

    /**
     * Attach a project to a KPI.
     *
     * @throws Throwable
     */
    public function attachProject(int $kpiId, int $projectId): array
    {
        return DB::transaction(function () use ($kpiId, $projectId) {
            // Check if already attached
            if ($this->repository->isAttached($kpiId, $projectId)) {
                return [
                    'success' => false,
                    'message' => 'This project is already attached to this KPI.',
                ];
            }

            // Verify KPI exists
            $kpi = $this->repository->getKpi($kpiId);
            if (! $kpi) {
                return [
                    'success' => false,
                    'message' => 'KPI not found.',
                ];
            }

            // Verify project exists
            $project = $this->repository->getProject($projectId);
            if (! $project) {
                return [
                    'success' => false,
                    'message' => 'Project not found.',
                ];
            }

            // Attach the project
            $this->repository->attach($kpiId, $projectId);

            // Create log entry
            $kpi->logs()->create([
                'user_id' => auth()->id(),
                'log_text' => "Project < $project->name > was attached to this KPI.",
            ]);

            // Load the attached project with its quarters & milestones
            $projectWithRelations = $this->repository->getProject($projectId);

            $formatted = [];
            if ($projectWithRelations) {
                $formattedProjects = $this->formatProjects(collect([$projectWithRelations]));
                $formatted = $formattedProjects[0] ?? [];
            }

            return [
                'success' => true,
                'message' => 'Project attached successfully.',
                'data' => [
                    // single project payload – frontend will append this project to the table
                    'project' => $formatted,
                ],
            ];
        });
    }

    /**
     * Detach a project from a KPI.
     */
    public function detachProject(int $kpiId, int $projectId): array
    {
        return DB::transaction(function () use ($kpiId, $projectId) {
            // Verify KPI exists
            $kpi = $this->repository->getKpi($kpiId);
            if (! $kpi) {
                return [
                    'success' => false,
                    'message' => 'KPI not found.',
                ];
            }

            // Get project before detaching for log
            $project = $this->repository->getProject($projectId);
            $projectName = $project->name ?? 'Unknown Project';

            // Check if attached
            if (! $this->repository->isAttached($kpiId, $projectId)) {
                return [
                    'success' => false,
                    'message' => 'This project is not attached to this KPI.',
                ];
            }

            // Detach the project
            $this->repository->detach($kpiId, $projectId);

            // Create log entry
            $kpi->logs()->create([
                'user_id' => auth()->id(),
                'log_text' => "Project < $projectName > was detached from this KPI.",
            ]);

            return [
                'success' => true,
                'message' => 'Project detached successfully.',
                // No need to send projects list – frontend will remove the project from DOM
                'data' => [],
            ];
        });
    }

    /**
     * Format projects with quarters and milestones for JSON response.
     */
    protected function formatProjects(Collection $projects): array
    {
        return $projects->unique('id')->values()->map(function ($project) {
            // Ensure quarters are unique by ID
            $uniqueQuarters = $project->quarters->unique('id')->values();

            return [
                'id' => $project->id,
                'name' => $project->name,
                'project_manager_name' => $project->project_manager_name,
                'status' => $project->status,
                'quarters' => $uniqueQuarters->map(function ($quarter) {
                    // Ensure milestones are unique by ID
                    $uniqueMilestones = $quarter->milestones->unique('id')->values();

                    return [
                        'id' => $quarter->id,
                        'quarter' => $quarter->quarter,
                        'milestones' => $uniqueMilestones->map(function ($milestone) {
                            return [
                                'id' => $milestone->id,
                                'milestone' => $milestone->milestone,
                                'status' => $milestone->status,
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }
}
