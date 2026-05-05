<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\change_request;

class ChangeRequestUserAssignment
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $changeRequest;
    public $request;
    public $creator;

    public bool $afterCommit = true;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(change_request $changeRequest, $request)
    {
        $this->changeRequest = $changeRequest;
        $this->request = $request;
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
