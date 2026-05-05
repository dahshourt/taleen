<?php

namespace App\Http\Controllers\CustomField;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFieldStatusRequest;
use App\Services\CustomField\CustomFieldStatusService;
use Illuminate\Http\JsonResponse;

class CustomFieldStatusController extends Controller
{
    public function __construct(
        private CustomFieldStatusService $service
    ) {}

    /**
     * Get log messages for a custom field
     */
    public function index($id): JsonResponse
    {
        try {
            $customFieldStatuses = $this->service->getByCustomFieldId((int) $id);
            
            return response()->json([
                'success' => true,
                'data' => $customFieldStatuses->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'status_id' => $item->status_id,
                        'status_name' => $item->status->status_name ?? null,
                        'log_message' => $item->log_message,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch log messages: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all active statuses for select dropdown
     */
    public function getActiveStatuses(): JsonResponse
    {
        try {
            $statuses = $this->service->getAllActiveStatuses();
            
            return response()->json([
                'success' => true,
                'data' => $statuses->map(function ($status) {
                    return [
                        'id' => $status->id,
                        'name' => $status->status_name,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statuses: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store or update log messages for a custom field
     */
    public function store(CustomFieldStatusRequest $request, $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            $statuses = $validated['statuses'] ?? [];
            
            $this->service->syncCustomFieldStatuses((int) $id, $statuses);
            
            return response()->json([
                'success' => true,
                'message' => 'Log messages saved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save log messages: ' . $e->getMessage(),
            ], 500);
        }
    }
}
