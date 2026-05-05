<?php

namespace App\Http\Controllers\KpiInitiative;

use App\Http\Controllers\Controller;
use App\Http\Requests\KpiInitiative\KpiInitiativeRequest;
use App\Services\KpiInitiative\KpiInitiativeService;
use App\Services\KpiPillar\KpiPillarService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KpiInitiativeController extends Controller
{
    protected $kpiInitiativeService;

    protected $kpiPillarService;

    protected $view;

    public function __construct(
        KpiInitiativeService $kpiInitiativeService,
        KpiPillarService $kpiPillarService
    ) {
        $this->kpiInitiativeService = $kpiInitiativeService;
        $this->kpiPillarService = $kpiPillarService;
        $this->view = 'kpi-initiatives';
        $view = 'kpi-initiatives';
        $route = 'kpi-initiatives';
        $title = 'KPI Initiatives';
        $form_title = 'KPI Initiative';
        view()->share(compact('view', 'route', 'title', 'form_title'));
    }

    /**
     * Display a listing of the KPI initiatives.
     */
    public function index(): View
    {
        $this->authorize('List KPI Initiatives');
        $collection = $this->kpiInitiativeService->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new KPI initiative.
     */
    public function create(): View
    {
        $this->authorize('Create KPI Initiatives');
        $pillars = $this->kpiPillarService->getAllActive();

        return view("$this->view.create", compact('pillars'));
    }

    /**
     * Store a newly created KPI initiative in storage.
     */
    public function store(KpiInitiativeRequest $request): RedirectResponse
    {
        $this->authorize('Create KPI Initiatives');
        try {
            $this->kpiInitiativeService->create($request->validated());

            return redirect()->route('kpi-initiatives.index')
                ->with('status', 'KPI Initiative created successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create KPI initiative: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified KPI initiative.
     */
    public function edit(int $id): View
    {
        $this->authorize('Edit KPI Initiatives');
        $row = $this->kpiInitiativeService->find($id);

        if (! $row) {
            abort(404, 'KPI Initiative not found');
        }

        $pillars = $this->kpiPillarService->getAllActive();

        return view("$this->view.edit", compact('row', 'pillars'));
    }

    /**
     * Update the specified KPI initiative in storage.
     */
    public function update(KpiInitiativeRequest $request, int $id): RedirectResponse
    {
        $this->authorize('Edit KPI Initiatives');
        try {
            $updated = $this->kpiInitiativeService->update($request->validated(), $id);

            if (! $updated) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'KPI Initiative not found');
            }

            return redirect()->route('kpi-initiatives.index')
                ->with('status', 'KPI Initiative updated successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update KPI initiative: ' . $e->getMessage());
        }
    }

    /**
     * Update the status of the specified KPI initiative.
     */
    public function updateStatus(): JsonResponse
    {
        $this->authorize('Edit KPI Initiatives');
        try {
            $id = request()->post('id');
            $updated = $this->kpiInitiativeService->toggleStatus($id);

            if (! $updated) {
                return response()->json([
                    'message' => 'KPI Initiative not found',
                    'status' => 'error',
                ], 404);
            }

            return response()->json([
                'message' => 'Status updated successfully',
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update status: ' . $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }
}
