<?php

namespace App\Events;

use App\Models\Change_request;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StatusChanged
{
    use Dispatchable, SerializesModels;

    public $changeRequest;
    public $oldStatusId;
    public $newStatusId;
    public $groupId;

    /**
     * Create a new event instance.
     *
     * @param Change_request $changeRequest
     * @param int $oldStatusId
     * @param int $newStatusId
     * @param int $groupId
     */
    public function __construct(Change_request $changeRequest, int $oldStatusId, int $newStatusId, int $groupId)
    {
        $this->changeRequest = $changeRequest;
        $this->oldStatusId = $oldStatusId;
        $this->newStatusId = $newStatusId;
        $this->groupId = $groupId;
    }
}
