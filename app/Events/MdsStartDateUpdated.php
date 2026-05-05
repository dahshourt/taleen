<?php

namespace App\Events;

use App\Models\ManDaysLog;
use App\Models\Change_request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when MDS (Man Days Schedule) start date is updated.
 * 
 * This event is used to trigger notifications when a technical team
 * updates the start date for their work on a CR.
 */
class MdsStartDateUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ManDaysLog $mdsLog;
    public Change_request $changeRequest;
    public ?string $oldStartDate;
    public string $newStartDate;
    public int $groupId;

    // Run event after the current database commit
    public bool $afterCommit = true;

    // Create a new event instance.
    public function __construct(
        ManDaysLog $mdsLog,
        Change_request $changeRequest,
        ?string $oldStartDate,
        string $newStartDate,
        int $groupId
    ) {
        $this->mdsLog = $mdsLog;
        $this->changeRequest = $changeRequest;
        $this->oldStartDate = $oldStartDate;
        $this->newStartDate = $newStartDate;
        $this->groupId = $groupId;
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
