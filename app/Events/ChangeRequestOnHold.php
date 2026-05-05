<?php

namespace App\Events;

use App\Models\Change_request;
use App\Models\HoldReason;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeRequestOnHold
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $changeRequest;

    public $holdData;

    public $holdReason;

    public $creator;

    // to run the event after the current commit
    public bool $afterCommit = true;

    /**
     * Create a new event instance.
     *
     * @param  Change_request  $changeRequest  The CR being put on hold
     * @param  array  $holdData  ['hold_reason_id', 'resuming_date', 'justification']
     * @return void
     */
    public function __construct(Change_request $changeRequest, array $holdData = [])
    {
        $this->changeRequest = $changeRequest;
        $this->holdData = $holdData;

        // Resolve the hold reason name
        $this->holdReason = isset($holdData['hold_reason_id'])
            ? HoldReason::find($holdData['hold_reason_id'])
            : null;

        $this->creator = $changeRequest->requester;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
