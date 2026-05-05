<?php

namespace App\Http\Controllers\CustomField;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomField\CustomFieldRequest;
use App\Services\CustomField\CustomFieldService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    protected $customFieldService;

    public function __construct(CustomFieldService $customFieldService)
    {
        $this->customFieldService = $customFieldService;
        $this->view = 'custom-fields';
        $view = 'custom-fields';
        $route = 'custom-fields';
        $title = 'Custom Fields';
        $form_title = 'Custom Field';
        $OtherRoute = 'custom-fields';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    /**
     * Display a listing of the custom fields.
     */
    public function index(): View
    {
        $this->authorize('List Custom Fields');

        $collection = $this->customFieldService->getAllCustomFields(true);

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new custom field.
     */
    public function create(): View
    {
        $this->authorize('Create Custom Fields');

        $inputTypes = $this->customFieldService->getInputTypes();

        return view("$this->view.create", compact('inputTypes'));
    }

    /**
     * Store a newly created custom field in storage.
     */
    public function store(CustomFieldRequest $request): RedirectResponse
    {
        $this->authorize('Create Custom Fields');

        try {
            $this->customFieldService->createCustomField($request->validated());

            return redirect()->back()
                ->with('status', 'Custom Field created successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create custom field: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified custom field.
     *
     * @param  int  $id
     */
    public function edit($id): View
    {
        $this->authorize('Edit Custom Fields');

        $row = $this->customFieldService->findCustomField($id);

        if (! $row) {
            abort(404, 'Custom Field not found');
        }

        $inputTypes = $this->customFieldService->getInputTypes();

        return view("$this->view.edit", compact('row', 'inputTypes'));
    }

    /**
     * Update the specified custom field in storage.
     *
     * @param  int  $id
     */
    public function update(CustomFieldRequest $request, $id): RedirectResponse
    {
        $this->authorize('Edit Custom Fields');

        try {
            $updated = $this->customFieldService->updateCustomField($request->validated(), $id);

            if (! $updated) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Custom Field not found');
            }

            return redirect()->back()
                ->with('status', 'Custom Field updated successfully');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update custom field: ' . $e->getMessage());
        }
    }

    /**
     * Update the status of the specified custom field.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $this->authorize('Edit Custom Fields');

        try {
            $id = $request->post('id');
            $updated = $this->customFieldService->updateCustomFieldStatus($id);

            if (! $updated) {
                return response()->json([
                    'message' => 'Custom Field not found',
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

    /**
     * Get table options for select/multiselect fields.
     */
    public function getTableOptions(Request $request): JsonResponse
    {
        $this->authorize('Create Custom Fields');

        try {
            $tableName = $request->get('table');

            if (! $tableName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Table name is required',
                ]);
            }

            // Validate table name to prevent SQL injection
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid table name format',
                ]);
            }

            $options = $this->customFieldService->getTableOptions($tableName);

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading table options: ' . $e->getMessage(),
            ]);
        }
    }
}
