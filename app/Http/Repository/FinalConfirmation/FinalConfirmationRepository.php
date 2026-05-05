<?php

namespace App\Http\Repository\FinalConfirmation;

use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\Status;
use Exception;
use Illuminate\Support\Facades\Log;

class FinalConfirmationRepository
{
    /**
     * Find change request by CR number
     */
    public function findCRByNumber(string $crNumber): ?Change_request
    {
        try {
            return Change_request::with('CurrentRequestStatuses.status')
                ->where('cr_no', $crNumber)
                ->first();

        } catch (Exception $e) {
            Log::error('Error finding CR by number in FinalConfirmationRepository', [
                'cr_number' => $crNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get current active status for a change request
     */
    public function getCurrentStatus(int $crId): ?Change_request_statuse
    {
        try {
            return Change_request_statuse::with('status')
                ->where('cr_id', $crId)
                ->where('active', '1')
                ->first();

        } catch (Exception $e) {
            Log::error('Error getting current status in FinalConfirmationRepository', [
                'cr_id' => $crId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
