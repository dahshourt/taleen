<?php

namespace App\Events;

use App\Models\Defect;
use App\Models\Change_request;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DefectStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Defect $defect;
    public int $groupId;
    public int $oldStatusId;
    public int $newStatusId;
    public Change_request $changeRequest;

    public bool $afterCommit = true;

    public function __construct(Defect $defect, int $groupId, int $oldStatusId, int $newStatusId, Change_request $changeRequest)
    {
        $this->defect = $defect;
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
