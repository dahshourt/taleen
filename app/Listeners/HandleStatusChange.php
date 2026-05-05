<?php

namespace App\Listeners;

use App\Events\StatusChanged;
use App\Models\Status;
use App\Services\Workflow\ParallelWorkflowService;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleStatusChange implements ShouldQueue
{
    /**
     * The number of times the queued listener may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [60, 180, 300];

    /**
     * @var ParallelWorkflowService
     */
    protected $workflowService;

    /**
     * Create the event listener.
     *
     * @param ParallelWorkflowService $workflowService
     */
    public function __construct(ParallelWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Handle the event.
     *
     * @param StatusChanged $event
     * @return void
     */
    public function handle(StatusChanged $event): void
    {
        $changeRequest = $event->changeRequest;
        $oldStatusId = $event->oldStatusId;
        $newStatusId = $event->newStatusId;
        $groupId = $event->groupId;

        try {
            // Check if this is a split status (start of parallel workflow)
            $splitStatus = Status::where('status_name', 'Pending Create Agreed Scope')->first();
            
            if ($splitStatus && $newStatusId === $splitStatus->id) {
                $joinStatus = Status::where('status_name', 'Pending Update Agreed Requirements')->first();
                
                if ($joinStatus) {
                    $this->workflowService->initiateParallelWorkflow(
                        $changeRequest,
                        $splitStatus->status_name,
                        $joinStatus->status_name
                    );
                    return;
                }
            }

            // Handle regular status updates for existing workflows
            $this->workflowService->handleStatusUpdate(
                $changeRequest,
                $oldStatusId,
                $newStatusId,
                $groupId
            );
        } catch (\Exception $e) {
            \Log::error('Error handling status change: ' . $e->getMessage(), [
                'change_request_id' => $changeRequest->id,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'group_id' => $groupId,
                'exception' => $e
            ]);
            
            // Re-throw to trigger retry if needed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param StatusChanged $event
     * @param \Throwable $exception
     * @return void
     */
    public function failed(StatusChanged $event, \Throwable $exception): void
    {
        \Log::error('Failed to process status change after maximum attempts', [
            'change_request_id' => $event->changeRequest->id,
            'old_status_id' => $event->oldStatusId,
            'new_status_id' => $event->newStatusId,
            'group_id' => $event->groupId,
            'exception' => $exception
        ]);
    }
}
