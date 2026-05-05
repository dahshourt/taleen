<?php

namespace App\Http\Controllers\KpiSubInitiative;

use App\Http\Controllers\Controller;
use App\Http\Requests\KpiSubInitiative\KpiSubInitiativeRequest;
use App\Services\KpiInitiative\KpiInitiativeService;
use App\Services\KpiSubInitiative\KpiSubInitiativeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KpiSubInitiativeController extends Controller
{
    protected $kpiSubInitiativeService;

    protected $kpiInitiativeService;

    protected $view;

    public function __construct(
        KpiSubInitiativeService $kpiSubInitiativeService,
        KpiInitiativeService $kpiInitiativeService
    ) {
        $this->kpiSubInitiativeService = $kpiSubInitiativeService;
        $this->kpiInitiativeService = $kpiInitiativeService;
        $this->view = 'kpi-sub-initiatives';
        $view = 'kpi-sub-initiatives';
        $route = 'kpi-sub-initiatives';
        $title = 'KPI Sub-Initiatives';
        $form_title = 'KPI Sub-Initiative';
        view()->share(compact('view', 'route', 'title', 'form_title'));
    }

    /**
     * Display a listing of the KPI sub-initiatives.
     */
    public function index(): View
    {
        $this->authorize('List KPI Sub-Initiatives');
        $collection = $this->kpiSubInitiativeService->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new KPI sub-initiative.
     */
    public function create(): View
    {
        $this->authorize('Create KPI Sub-Initiatives');
        $initiatives = $this->kpiInitiativeService->getAllActive();

        return view("$this->view.create", compact('initiatives'));
    }

    /**
     * Store a newly created KPI sub-initiative in storage.
     */
    public function store(KpiSubInitiativeRequest $request): RedirectResponse
    {
        $this->authorize('Create KPI Sub-Initiatives');
        try {
            $this->kpiSubInitiativeService->create($request->validated());

            return redirect()->route('kpi-sub-initiatives.index')
                ->with('status', 'KPI Sub-Initiative created successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create KPI sub-initiative: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified KPI sub-initiative.
     */
    public function edit(int $id): View
    {
        $this->authorize('Edit KPI Sub-Initiatives');
        $row = $this->kpiSubInitiativeService->find($id);

        if (! $row) {
            abort(404, 'KPI Sub-Initiative not found');
        }

        $initiatives = $this->kpiInitiativeService->getAllActive();

        return view("$this->view.edit", compact('row', 'initiatives'));
    }

    /**
     * Update the specified KPI sub-initiative in storage.
     */
    public function update(KpiSubInitiativeRequest $request, int $id): RedirectResponse
    {
        $this->authorize('Edit KPI Sub-Initiatives');
        try {
            $updated = $this->kpiSubInitiativeService->update($request->validated(), $id);

            if (! $updated) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'KPI Sub-Initiative not found');
            }

            return redirect()->route('kpi-sub-initiatives.index')
                ->with('status', 'KPI Sub-Initiative updated successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update KPI sub-initiative: ' . $e->getMessage());
        }
    }

    /**
     * Update the status of the specified KPI sub-initiative.
     */
    public function updateStatus(): JsonResponse
    {
        $this->authorize('Edit KPI Sub-Initiatives');
        try {
            $id = request()->post('id');
            $updated = $this->kpiSubInitiativeService->toggleStatus($id);

            if (! $updated) {
                return response()->json([
                    'message' => 'KPI Sub-Initiative not found',
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
