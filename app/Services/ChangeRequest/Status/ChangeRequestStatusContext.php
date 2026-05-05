<?php

namespace App\Services\ChangeRequest\Status;

use App\Models\Change_request as ChangeRequest;
use App\Models\NewWorkFlow;
use Illuminate\Http\Request;

class ChangeRequestStatusContext
{
    public function __construct(
        public ChangeRequest $changeRequest,
        public array $statusData,
        public ?NewWorkFlow $workflow,
        public int $userId,
        public $request // Keep the original request object for now as some parts (Creator) might still need specific fields from it
    ) {
    }
}
