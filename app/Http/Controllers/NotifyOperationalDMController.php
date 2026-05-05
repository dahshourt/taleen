<?php

namespace App\Http\Controllers;

use App\Events\ChangeRequestStatusUpdated;
use App\Events\NotifyOperationalDM;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Services\StatusConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifyOperationalDMController extends Controller
{
    public function __construct(public ChangeRequestRepository $changeRequestRepository){}

    public function send(Request $request): JsonResponse
    {
        $this->authorize('notify_DMs');

        $cr_id = $request->post('cr_id');

        if ($cr_id) {
            $cr = $this->changeRequestRepository->findById($cr_id);
            NotifyOperationalDM::dispatch($cr);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully!'
        ]);
    }
}
