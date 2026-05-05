<?php

namespace App\Events;

use App\Models\change_request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeRequestCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $changeRequest;

    public $statusData;

    public $creator;

    // to run the event after the current commit
    public bool $afterCommit = true;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(change_request $changeRequest, array $statusData = [])
    {
        $this->changeRequest = $changeRequest;
        $this->statusData = $statusData;
        // Creator is the requester
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
