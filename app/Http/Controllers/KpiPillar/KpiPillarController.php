<?php

namespace App\Http\Controllers\KpiPillar;

use App\Http\Controllers\Controller;
use App\Http\Requests\KpiPillar\KpiPillarRequest;
use App\Services\KpiPillar\KpiPillarService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KpiPillarController extends Controller
{
    protected $kpiPillarService;

    protected $view;

    public function __construct(KpiPillarService $kpiPillarService)
    {
        $this->kpiPillarService = $kpiPillarService;
        $this->view = 'kpi-pillars';
        $view = 'kpi-pillars';
        $route = 'kpi-pillars';
        $title = 'KPI Pillars';
        $form_title = 'KPI Pillar';
        view()->share(compact('view', 'route', 'title', 'form_title'));
    }

    /**
     * Display a listing of the KPI pillars.
     */
    public function index(): View
    {
        $this->authorize('List KPI Pillars');
        $collection = $this->kpiPillarService->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new KPI pillar.
     */
    public function create(): View
    {
        $this->authorize('Create KPI Pillars');

        return view("$this->view.create");
    }

    /**
     * Store a newly created KPI pillar in storage.
     */
    public function store(KpiPillarRequest $request): RedirectResponse
    {
        $this->authorize('Create KPI Pillars');
        try {
            $this->kpiPillarService->create($request->validated());

            return redirect()->route('kpi-pillars.index')
                ->with('status', 'KPI Pillar created successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create KPI pillar: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified KPI pillar.
     */
    public function edit(int $id): View
    {
        $this->authorize('Edit KPI Pillars');
        $row = $this->kpiPillarService->find($id);

        if (! $row) {
            abort(404, 'KPI Pillar not found');
        }

        return view("$this->view.edit", compact('row'));
    }

    /**
     * Update the specified KPI pillar in storage.
     */
    public function update(KpiPillarRequest $request, int $id): RedirectResponse
    {
        $this->authorize('Edit KPI Pillars');
        try {
            $updated = $this->kpiPillarService->update($request->validated(), $id);

            if (! $updated) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'KPI Pillar not found');
            }

            return redirect()->route('kpi-pillars.index')
                ->with('status', 'KPI Pillar updated successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update KPI pillar: ' . $e->getMessage());
        }
    }

    /**
     * Update the status of the specified KPI pillar.
     */
    public function updateStatus(): JsonResponse
    {
        $this->authorize('Edit KPI Pillars');
        try {
            $id = request()->post('id');
            $updated = $this->kpiPillarService->toggleStatus($id);

            if (! $updated) {
                return response()->json([
                    'message' => 'KPI Pillar not found',
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
