<?php

namespace App\Events;

use App\Models\Prerequisite;
use App\Models\Change_request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrerequisiteStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Prerequisite $prerequisite;
    public int $groupId;
    public int $oldStatusId;
    public int $newStatusId;
    public Change_request $changeRequest;
    public bool $afterCommit = true;

    public function __construct(Prerequisite $prerequisite, int $groupId, int $oldStatusId, int $newStatusId    , Change_request $changeRequest)
    {
        $this->prerequisite = $prerequisite;
        $this->groupId = $groupId;
        $this->oldStatusId = $oldStatusId;
        $this->newStatusId = $newStatusId;
        $this->changeRequest = $changeRequest;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
