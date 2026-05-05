<?php

namespace App\Http\Controllers;

use App\Services\HoldReasonService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HoldReasonController extends Controller
{
    protected $holdReasonService;

    private $view = 'hold-reasons';

    public function __construct(HoldReasonService $holdReasonService)
    {
        $this->holdReasonService = $holdReasonService;

        $title = 'Hold Reasons';
        $view = 'hold-reasons';
        $route = 'hold-reasons';
        view()->share(compact('view', 'title', 'route'));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $this->authorize('List Hold Reasons');

            $collection = $this->holdReasonService->getAllHoldReasons();
            $title = 'Hold Reasons';

            return view("{$this->view}.index", compact('collection', 'title'));
        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to hold reasons list', [
                'user_id' => auth()->id(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $this->authorize('Create Hold Reasons');

            $title = 'Create Hold Reason';

            return view("{$this->view}.create", compact('title'));
        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to create hold reason', [
                'user_id' => auth()->id(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->authorize('Create Hold Reasons');

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:hold_reasons,name',
                'status' => 'required|in:0,1',
            ], [
                'name.required' => 'Hold reason name is required.',
                'name.unique' => 'This hold reason already exists.',
                'name.max' => 'Hold reason name must not exceed 255 characters.',
                'status.required' => 'Status is required.',
                'status.in' => 'Invalid status value.',
            ]);

            $this->holdReasonService->createHoldReason($validated);

            return redirect()->route('hold-reasons.index');

        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized attempt to create hold reason', [
                'user_id' => auth()->id(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to perform this action.');
        } catch (Exception $e) {
            Log::error('Failed to create hold reason', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create hold reason. Please try again.')
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id)
    {
        try {
            $this->authorize('Edit Hold Reasons');

            $row = $this->holdReasonService->findHoldReason($id);

            if (! $row) {
                return redirect()->route('hold-reasons.index')
                    ->with('error', 'Hold reason not found.');
            }

            $title = 'Edit Hold Reason';

            return view("{$this->view}.edit", compact('row', 'title'));
        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to edit hold reason', [
                'user_id' => auth()->id(),
                'hold_reason_id' => $id,
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        try {
            $this->authorize('Edit Hold Reasons');

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:hold_reasons,name,' . $id,
                'status' => 'required|in:0,1',
            ], [
                'name.required' => 'Hold reason name is required.',
                'name.unique' => 'This hold reason already exists.',
                'name.max' => 'Hold reason name must not exceed 255 characters.',
                'status.required' => 'Status is required.',
                'status.in' => 'Invalid status value.',
            ]);

            $updated = $this->holdReasonService->updateHoldReason($validated, $id);

            if (! $updated) {
                return redirect()->back()
                    ->with('error', 'Hold reason not found.')
                    ->withInput();
            }

            return redirect()->route('hold-reasons.index');

        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized attempt to update hold reason', [
                'user_id' => auth()->id(),
                'hold_reason_id' => $id,
            ]);

            return redirect('/')->with('error', 'You do not have permission to perform this action.');
        } catch (Exception $e) {
            Log::error('Failed to update hold reason', [
                'id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()
                ->with('error', 'Failed to update hold reason. Please try again.')
                ->withInput();
        }
    }

    /**
     * Update status via AJAX
     */
    public function updateStatus(Request $request)
    {
        try {
            $this->authorize('Edit Hold Reasons');

            $validated = $request->validate([
                'id' => 'required|exists:hold_reasons,id',
                'status' => 'required|in:0,1',
            ]);

            $updated = $this->holdReasonService->updateHoldReason([
                'status' => $validated['status'],
            ], $validated['id']);

            if (! $updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hold reason not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully.',
            ]);

        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        } catch (Exception $e) {
            Log::error('Failed to update hold reason status', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status.',
            ], 500);
        }
    }
}
