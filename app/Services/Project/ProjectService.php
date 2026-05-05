<?php

namespace App\Services\Project;

use App\Http\Repository\Project\ProjectRepository;
use App\Models\Project;
use App\Models\ProjectKpiMilestone;
use App\Models\ProjectKpiQuarter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(private ProjectRepository $projectRepository) {}

    public function getAll(): Collection
    {
        return $this->projectRepository->getAll();
    }

    /**
     * Lightweight list of projects for dropdowns/selects.
     */
    public function listAll()
    {
        return $this->projectRepository->listAll();
    }

    /**
     * Projects not linked to any KPI.
     */
    public function listUnlinked()
    {
        return $this->projectRepository->listUnlinked();
    }

    public function find(int $id): ?Project
    {
        return $this->projectRepository->find($id);
    }

    public function create(array $data, int $userId): Project
    {
        return DB::transaction(function () use ($data, $userId) {
            // Create the project
            $project = $this->projectRepository->create([
                'name' => $data['name'],
                'status' => $data['status'],
                'project_manager_name' => $data['project_manager_name'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Create quarters and milestones
            if (isset($data['quarters']) && is_array($data['quarters'])) {
                foreach ($data['quarters'] as $quarterData) {
                    $quarter = ProjectKpiQuarter::create([
                        'project_id' => $project->id,
                        'quarter' => $quarterData['quarter'],
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    if (isset($quarterData['milestones']) && is_array($quarterData['milestones'])) {
                        foreach ($quarterData['milestones'] as $milestoneData) {
                            if (! empty($milestoneData['milestone'])) {
                                ProjectKpiMilestone::create([
                                    'project_kpi_quarter_id' => $quarter->id,
                                    'milestone' => $milestoneData['milestone'],
                                    'status' => $milestoneData['status'] ?? 'Not Started',
                                    'created_by' => $userId,
                                    'updated_by' => $userId,
                                ]);
                            }
                        }
                    }
                }
            }

            return $project->load(['quarters.milestones']);
        });
    }

    public function update(array $data, int $id, int $userId): bool
    {
        return DB::transaction(function () use ($data, $id, $userId) {
            // Update the project
            $updated = $this->projectRepository->update([
                'name' => $data['name'],
                'status' => $data['status'],
                'project_manager_name' => $data['project_manager_name'],
                'updated_by' => $userId,
            ], $id);

            if (! $updated) {
                return false;
            }

            $project = $this->find($id);

            // Handle quarters and milestones
            if (isset($data['quarters']) && is_array($data['quarters']) && count($data['quarters']) > 0) {
                $existingQuarterIds = [];

                foreach ($data['quarters'] as $quarterData) {
                    $quarter = null;

                    if (isset($quarterData['id']) && $quarterData['id']) {
                        // Update existing quarter
                        $quarter = ProjectKpiQuarter::withTrashed()->find($quarterData['id']);
                        if ($quarter && $quarter->project_id == $id) {
                            $quarter->update([
                                'quarter' => $quarterData['quarter'],
                                'updated_by' => $userId,
                            ]);

                            // Restore if it was soft deleted
                            if ($quarter->trashed()) {
                                $quarter->restore();
                            }

                            $existingQuarterIds[] = $quarter->id;
                        } else {
                            // If quarter not found or doesn't belong to this project, skip it
                            continue;
                        }
                    } else {
                        // Create new quarter
                        $quarter = ProjectKpiQuarter::create([
                            'project_id' => $id,
                            'quarter' => $quarterData['quarter'],
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);
                        $existingQuarterIds[] = $quarter->id;
                    }

                    // Handle milestones only if we have a valid quarter
                    if ($quarter && isset($quarterData['milestones']) && is_array($quarterData['milestones'])) {
                        $existingMilestoneIds = [];

                        foreach ($quarterData['milestones'] as $milestoneData) {
                            if (! empty($milestoneData['milestone'])) {
                                if (isset($milestoneData['id']) && $milestoneData['id']) {
                                    // Update existing milestone
                                    $milestone = ProjectKpiMilestone::withTrashed()->find($milestoneData['id']);
                                    if ($milestone && $milestone->project_kpi_quarter_id == $quarter->id) {
                                        $milestone->update([
                                            'milestone' => $milestoneData['milestone'],
                                            'status' => $milestoneData['status'] ?? 'Not Started',
                                            'updated_by' => $userId,
                                        ]);

                                        // Restore if it was soft deleted
                                        if ($milestone->trashed()) {
                                            $milestone->restore();
                                        }

                                        $existingMilestoneIds[] = $milestone->id;
                                    }
                                } else {
                                    // Create new milestone
                                    $milestone = ProjectKpiMilestone::create([
                                        'project_kpi_quarter_id' => $quarter->id,
                                        'milestone' => $milestoneData['milestone'],
                                        'status' => $milestoneData['status'] ?? 'Not Started',
                                        'created_by' => $userId,
                                        'updated_by' => $userId,
                                    ]);
                                    $existingMilestoneIds[] = $milestone->id;
                                }
                            }
                        }

                        // Soft delete milestones that were removed
                        // Get all non-deleted milestones for this quarter
                        $currentMilestoneIds = ProjectKpiMilestone::where('project_kpi_quarter_id', $quarter->id)
                            ->whereNull('deleted_at')
                            ->pluck('id')
                            ->toArray();

                        // Find milestones that exist in DB but not in the submitted data
                        $milestonesToDelete = array_diff($currentMilestoneIds, $existingMilestoneIds);

                        // Soft delete them
                        if (! empty($milestonesToDelete)) {
                            ProjectKpiMilestone::whereIn('id', $milestonesToDelete)->delete();
                        }
                    }
                }

                // Soft delete quarters that were explicitly marked for deletion
                if (isset($data['deleted_quarter_ids']) && is_array($data['deleted_quarter_ids']) && ! empty($data['deleted_quarter_ids'])) {
                    ProjectKpiQuarter::whereIn('id', $data['deleted_quarter_ids'])
                        ->where('project_id', $id)
                        ->delete();
                }
            }

            return true;
        });
    }

    public function delete(int $id): bool
    {
        return $this->projectRepository->delete($id);
    }
}
