<?php

namespace App\Events;

use App\Models\Change_request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CrDeliveredEvent
 * 
 * Fired when a Change Request reaches the "Delivered" status.
 * This event is used to check if any other CRs depend on this CR
 * and should be released from their dependency hold.
 * 
 * This is separate from ChangeRequestStatusUpdated to avoid coupling
 * with the notification system.
 */
class CrDeliveredEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The CR that was delivered
     */
    public Change_request $deliveredCr;

    /**
     * The CR number for easy access
     */
    public int $crNo;

    /**
     * Create a new event instance.
     */
    public function __construct(Change_request $deliveredCr)
    {
        $this->deliveredCr = $deliveredCr;
        $this->crNo = $deliveredCr->cr_no;
    }
}
