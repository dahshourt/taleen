<?php

namespace App\Http\Controllers\KpiProject;

use App\Http\Controllers\Controller;
use App\Services\KpiProject\KpiProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiProjectController extends Controller
{
    protected $service;

    public function __construct(KpiProjectService $service)
    {
        $this->service = $service;
    }

    /**
     * Attach a project to a KPI.
     * 
     * @param Request $request
     * @param int $kpiId
     * @return JsonResponse
     */
    public function attach(Request $request, int $kpiId): JsonResponse
    {
        $this->authorize('Edit KPIs');

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $result = $this->service->attachProject($kpiId, $validated['project_id']);

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * Detach a project from a KPI.
     * 
     * @param int $kpiId
     * @param int $projectId
     * @return JsonResponse
     */
    public function detach(int $kpiId, int $projectId): JsonResponse
    {
        $this->authorize('Edit KPIs');

        $result = $this->service->detachProject($kpiId, $projectId);

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }
}

