<?php

namespace App\Http\Controllers\Project;

use App\Exports\ProjectsExport;
use App\Exports\KpiProjectsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectRequest;
use App\Models\Project;
use App\Models\ProjectKpiMilestone;
use App\Models\ProjectKpiQuarter;
use App\Services\Project\ProjectService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    protected $projectService;
    protected $view;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
        $this->view = 'projects';
        $view = 'projects';
        $route = 'projects';
        $title = 'Project Manager KPIs';
        $form_title = 'Project Manager KPI';
        view()->share(compact('view', 'route', 'title', 'form_title'));
    }

    /**
     * Display a listing of the projects.
     */
    public function index(): View
    {
        $this->authorize('List Projects');
        $collection = $this->projectService->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): View
    {
        $this->authorize('Create Projects');
        $statuses = Project::STATUS;
        $quarters = ProjectKpiQuarter::QUARTER;
        $milestoneStatuses = ProjectKpiMilestone::STATUS;

        return view("$this->view.create", compact('statuses', 'quarters', 'milestoneStatuses'));
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(ProjectRequest $request): RedirectResponse
    {
        $this->authorize('Create Projects');
        try {
            $data = $request->validated();
            $userId = auth()->id();

            $this->projectService->create($data, $userId);

            return redirect()->route('projects.index')
                ->with('status', 'Project created successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create project: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(int $id): View
    {
        $this->authorize('Edit Projects');
        $row = $this->projectService->find($id);

        if (!$row) {
            abort(404, 'Project not found');
        }

        $statuses = Project::STATUS;
        $quarters = ProjectKpiQuarter::QUARTER;
        $milestoneStatuses = ProjectKpiMilestone::STATUS;

        return view("$this->view.edit", compact('row', 'statuses', 'quarters', 'milestoneStatuses'));
    }

    /**
     * Update the specified project in storage.
     */
    public function update(ProjectRequest $request, int $id): RedirectResponse
    {
        $this->authorize('Edit Projects');
        try {
            $data = $request->validated();
            $userId = auth()->id();

            $updated = $this->projectService->update($data, $id, $userId);

            if (!$updated) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Project not found');
            }

            return redirect()->route('projects.index')
                ->with('status', 'Project updated successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update project: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete a milestone.
     */
    public function deleteMilestone(Request $request): JsonResponse
    {
        $this->authorize('Edit Projects');
        try {
            $milestoneId = $request->input('milestone_id');
            $milestone = ProjectKpiMilestone::find($milestoneId);

            if (!$milestone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Milestone not found',
                ], 404);
            }

            $milestone->delete();

            return response()->json([
                'success' => true,
                'message' => 'Milestone deleted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete milestone: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export projects to Excel.
     */
    public function export(): BinaryFileResponse
    {
        $this->authorize('List Projects');
        return Excel::download(new ProjectsExport, 'project-manager-kpis-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Export projects for a specific KPI to Excel.
     */
    public function exportByKpi(int $kpiId): BinaryFileResponse
    {
        $this->authorize('List Projects');
        return Excel::download(
            new KpiProjectsExport($kpiId),
            'project-manager-kpis-kpi-' . $kpiId . '-' . date('Y-m-d') . '.xlsx'
        );
    }
}
