<?php

namespace App\Events;

use App\Models\Defect;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Change_request;

class DefectCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Defect $defect;
    public int $groupId;
    public int $statusId;
    public Change_request $changeRequest;

    public bool $afterCommit = true;

    public function __construct(Defect $defect, int $groupId, int $statusId, Change_request $changeRequest)
    {
        $this->defect = $defect;
        $this->groupId = $groupId;
        $this->statusId = $statusId;
        $this->changeRequest = $changeRequest;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
