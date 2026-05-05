<?php

namespace App\Http\Controllers\KpiType;

use App\Http\Controllers\Controller;
use App\Http\Requests\KpiType\KpiTypeRequest;
use App\Services\KpiType\KpiTypeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KpiTypeController extends Controller
{
    protected $kpiTypeService;

    protected $view;

    public function __construct(KpiTypeService $kpiTypeService)
    {
        $this->kpiTypeService = $kpiTypeService;
        $this->view = 'kpi-types';
        $view = 'kpi-types';
        $route = 'kpi-types';
        $title = 'KPI Types';
        $form_title = 'KPI Type';
        view()->share(compact('view', 'route', 'title', 'form_title'));
    }

    /**
     * Display a listing of the KPI types.
     */
    public function index(): View
    {
        $this->authorize('List KPI Types');
        $collection = $this->kpiTypeService->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new KPI type.
     */
    public function create(): View
    {
        $this->authorize('Create KPI Types');

        return view("$this->view.create");
    }

    /**
     * Store a newly created KPI type in storage.
     */
    public function store(KpiTypeRequest $request): RedirectResponse
    {
        $this->authorize('Create KPI Types');
        try {
            $this->kpiTypeService->create($request->validated());

            return redirect()->route('kpi-types.index')
                ->with('status', 'KPI Type created successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create KPI type: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified KPI type.
     */
    public function edit(int $id): View
    {
        $this->authorize('Edit KPI Types');
        $row = $this->kpiTypeService->find($id);

        if (! $row) {
            abort(404, 'KPI Type not found');
        }

        return view("$this->view.edit", compact('row'));
    }

    /**
     * Update the specified KPI type in storage.
     */
    public function update(KpiTypeRequest $request, int $id): RedirectResponse
    {
        $this->authorize('Edit KPI Types');
        try {
            $updated = $this->kpiTypeService->update($request->validated(), $id);

            if (! $updated) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'KPI Type not found');
            }

            return redirect()->route('kpi-types.index')
                ->with('status', 'KPI Type updated successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update KPI type: ' . $e->getMessage());
        }
    }

    /**
     * Update the status of the specified KPI type.
     */
    public function updateStatus(): JsonResponse
    {
        $this->authorize('Edit KPI Types');
        try {
            $id = request()->post('id');
            $updated = $this->kpiTypeService->toggleStatus($id);

            if (! $updated) {
                return response()->json([
                    'message' => 'KPI Type not found',
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
