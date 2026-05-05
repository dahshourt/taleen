<?php

namespace App\Listeners;

use App\Events\CrDeliveredEvent;
use App\Services\ChangeRequest\CrDependencyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * ReleaseDependentCrsListener
 * 
 * Listens for CrDeliveredEvent and triggers the release of any CRs
 * that were held waiting for this CR to be delivered.
 */
class ReleaseDependentCrsListener implements ShouldQueue
{
    
    public int $tries = 3;

    
    public int $backoff = 10;

    protected CrDependencyService $dependencyService;


    public function __construct(CrDependencyService $dependencyService)
    {
        $this->dependencyService = $dependencyService;
    }

    public function handle(CrDeliveredEvent $event): void
    {
        Log::info("ReleaseDependentCrsListener: Processing CR delivery", [
            'cr_id' => $event->deliveredCr->id,
            'cr_no' => $event->crNo,
        ]);

        try {
            $this->dependencyService->handleCrDelivered($event->deliveredCr);
        } catch (\Exception $e) {
            Log::error("ReleaseDependentCrsListener: Failed to process CR delivery", [
                'cr_id' => $event->deliveredCr->id,
                'cr_no' => $event->crNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(CrDeliveredEvent $event, \Throwable $exception): void
    {
        Log::critical("ReleaseDependentCrsListener: Permanently failed to process CR delivery", [
            'cr_id' => $event->deliveredCr->id,
            'cr_no' => $event->crNo,
            'error' => $exception->getMessage(),
        ]);
    }
}
