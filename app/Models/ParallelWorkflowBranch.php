<?php

// app/Models/ParallelWorkflowBranch.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParallelWorkflowBranch extends Model
{
    protected $table = 'parallel_workflow_branches';
    protected $guarded = [];
    public $timestamps = true;

    public function tracking(): BelongsTo
    {
        return $this->belongsTo(ParallelWorkflow::class, 'tracking_id');
    }

    public function updateStatus(int $statusId): void
    {
        $this->update(['current_status_id' => $statusId]);

        if ($statusId === $this->end_status_id) {
            $this->update(['is_completed' => true]);
            $this->tracking->markBranchAsCompleted($this->id);
        }
    }
}