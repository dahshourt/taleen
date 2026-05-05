<?php

namespace App\Events;

use App\Models\change_request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChangeRequestStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $changeRequest;

    public $statusData;

    public $request;

    public $active_flag;

    public $newStatusIds; // Array of actual status IDs from the new workflow

    public $creator;

    // to run the event after the current commit
    public bool $afterCommit = true;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(change_request $changeRequest, array $statusData, $request, $active_flag)
    {
        $this->changeRequest = $changeRequest;
        $this->statusData = $statusData;
        $this->request = $request;
        $this->active_flag = $active_flag;
        // Important note: number 2.
        // Get actual status IDs from workflow
        // 2: $statusData['new_status_id'] is actually new_workflow_id
        // Get the actual status IDs that this workflow transitions to
        $this->newStatusIds = \App\Models\NewWorkFlowStatuses::where('new_workflow_id', $statusData['new_status_id'] ?? null)
            ->pluck('to_status_id')
            ->toArray();
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
