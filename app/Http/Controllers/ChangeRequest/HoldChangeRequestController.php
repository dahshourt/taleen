<?php

namespace App\Http\Controllers\ChangeRequest;

use App\Events\ChangeRequestOnHold;
use App\Http\Controllers\Controller;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Requests\Change_Request\HoldCRRequest;
use App\Models\Change_request;
use App\Services\ChangeRequest\ChangeRequestSchedulingService;
use App\Services\ChangeRequest\ChangeRequestService;
use App\Services\HoldReasonService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HoldChangeRequestController extends Controller
{
    public function __construct(
        private ChangeRequestRepository $changeRequestRepository,
        private ChangeRequestService $changeRequestService,
        private HoldReasonService $holdReasonService,
        private ChangeRequestSchedulingService $changeRequestSchedulingService
    ) {}

    public function holdCr()
    {
        try {
            $this->authorize('show hold cr');

            $collection = $this->changeRequestRepository->cr_hold_promo();
            $holdReasons = $this->holdReasonService->getActiveHoldReasons();

            return view('change_request.cr_hold_promo', compact('collection', 'holdReasons'));
        } catch (AuthorizationException $e) {
            Log::warning('Unauthorized access attempt to division manager CRs', [
                'user_id' => auth()->id(),
            ]);

            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    public function holdChangeRequest(HoldCRRequest $request): ?RedirectResponse
    {
        $this->authorize('show hold cr');

        try {
            $validated = $request->validated();
            $crId = $validated['change_request_id'];

            $holdData = [
                'hold_reason_id' => $validated['hold_reason_id'],
                'resuming_date' => $validated['resuming_date'],
                'justification' => $validated['justification'] ?? null,
            ];

            $result = $this->changeRequestSchedulingService->holdPromo($crId, $holdData);

            if ($result['status']) {
                $cr = Change_request::where('cr_no', $crId)->first();
                if ($cr) {
                    event(new ChangeRequestOnHold($cr, $holdData));
                }

                return redirect()->back()->with('success', $result['message']);
            }

            return redirect()->back()
                ->with('error', $result['message'])
                ->withInput();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (Exception $e) {
            Log::error('Error holding change request', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()
                ->with('error', 'An error occurred while putting the change request on hold. Please try again.')
                ->withInput();
        }
    }

    public function processHoldDecision(Request $request): ?JsonResponse
    {
        $this->authorize('show hold cr');

        try {
            $message = $this->changeRequestService->approvedContinue($request);

            return response()->json([
                'status' => 200,
                'isSuccess' => true,
                'message' => $message,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
