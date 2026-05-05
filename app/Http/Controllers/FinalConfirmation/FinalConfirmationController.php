<?php

namespace App\Http\Controllers\FinalConfirmation;
use App\Http\Controllers\Controller;
use App\Http\Repository\FinalConfirmation\FinalConfirmationRepository;
use App\Services\FinalConfirmation\FinalConfirmationService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class FinalConfirmationController extends Controller
{
    private $finalConfirmationService;

    private $finalConfirmationRepository;

    private $view = 'final-confirmation';

    public function __construct(
        FinalConfirmationService $finalConfirmationService,
        FinalConfirmationRepository $finalConfirmationRepository
    ) {
        $this->finalConfirmationService = $finalConfirmationService;
        $this->finalConfirmationRepository = $finalConfirmationRepository;

        $this->shareViewData();
    }

    /**
     * Display the final confirmation search form
     * If cr_no query parameter is provided, automatically search and display CR details
     */
    public function index(Request $request)
    {
        try {
            $this->authorize('Final Confirmation');

            $crDetails = null;
            $searchCrNumber = null;

            // Check if cr_no is provided in query parameters
            if ($request->has('cr_no') && ! empty($request->query('cr_no'))) {
                $searchCrNumber = $request->query('cr_no');

                // Search for the CR
                $crDetails = $this->finalConfirmationRepository->findCRByNumber($searchCrNumber);

                if (! $crDetails) {
                    $message = "Change Request #{$searchCrNumber} not found.";

                    return view("{$this->view}.index", [
                        'searchCrNumber' => $searchCrNumber,
                        'searchError' => $message,
                    ]);
                }
            }

            return view("{$this->view}.index", [
                'searchCrNumber' => $searchCrNumber,
                'crDetails' => $crDetails ?? null,
            ]);

        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to final confirmation', [
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        } catch (Throwable $e) {
            Log::error('Error in final confirmation index', [
                'cr_number' => $request->query('cr_no'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return view("{$this->view}.index", [
                'searchCrNumber' => $request->query('cr_no'),
                'searchError' => 'An error occurred while loading the Change Request.',
            ]);
        }
    }

    /**
     * Process the final confirmation action (reject/cancel)
     */
    public function submit(Request $request)
    {
        try {
            $this->authorize('Final Confirmation');

            // Validate the request
            $validator = $this->validateRequest($request);
            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            DB::beginTransaction();

            // Process the final confirmation
            $result = $this->finalConfirmationService->processFinalConfirmation(
                $request->cr_number,
                (int) $request->action,
                auth()->id(),
                $request->technical_feedback
            );

            if ($result['success']) {
                DB::commit();

                Log::info('Final confirmation processed successfully', [
                    'cr_number' => $request->cr_number,
                    'action' => $request->action,
                    'user_id' => auth()->id(),
                ]);

                return redirect()->back()->with('success', $result['message']);
            }
            DB::rollBack();

            return redirect()->back()->with('error', $result['message'])->withInput();

        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized final confirmation attempt', [
                'user_id' => auth()->id(),
                'cr_number' => $request->cr_number ?? 'unknown',
            ]);

            return redirect('/')->with('error', 'You do not have permission to perform this action.');

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to process final confirmation', [
                'cr_number' => $request->cr_number ?? 'unknown',
                'action' => $request->action ?? 'unknown',
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to process final confirmation. Please try again.');
        }
    }

    /**
     * Share common view data across all views
     */
    private function shareViewData(): void
    {
        view()->share([
            'view' => $this->view,
            'route' => 'final_confirmation',
            'title' => 'Final Confirmation',
            'form_title' => 'Final CR Confirmation',
        ]);
    }

    /**
     * Validate the final confirmation request
     */
    private function validateRequest(Request $request)
    {
        $rejectStatusId = \App\Services\StatusConfigService::getStatusId('Reject');
        $cancelStatusId = \App\Services\StatusConfigService::getStatusId('Cancel');

        return Validator::make($request->all(), [
            'cr_number' => 'required|string',
            'action' => "required|integer|in:{$rejectStatusId},{$cancelStatusId}",
            'technical_feedback' => 'required|string',
        ], [
            'cr_number.required' => 'CR number is required',
            'action.required' => 'Action is required',
            'action.integer' => 'Action must be a valid status ID',
            'action.in' => 'Invalid status ID provided',
            'technical_feedback.required' => 'Technical feedback is required',
            'technical_feedback.string' => 'Technical feedback must be a valid text',
        ]);
    }
}
