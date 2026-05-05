<?php

// app/Models/ParallelWorkflow.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParallelWorkflow extends Model
{
    protected $table = 'parallel_workflow_tracking';
    protected $guarded = [];
    public $timestamps = true;

    public function branches(): HasMany
    {
        return $this->hasMany(ParallelWorkflowBranch::class, 'tracking_id');
    }

    public function markBranchAsCompleted(int $branchId): void
    {
        $branch = $this->branches()->findOrFail($branchId);
        $branch->update(['is_completed' => true]);

        $this->increment('completed_workflows');

        if ($this->completed_workflows >= $this->required_completions) {
            $this->update(['is_completed' => true]);
            \App\Models\Change_request::find($this->cr_id)
                ->update(['status_id' => $this->join_status_id]);
        }
    }
}