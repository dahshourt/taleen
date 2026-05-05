<?php

namespace App\Models\Concerns;

use App\Models\Change_request_statuse;
use App\Models\GroupStatuses;
use App\Models\NewWorkFlow;
use App\Models\TechnicalCr;
use App\Services\StatusConfigService;
use Exception;

trait HasChangeRequestStatus
{
    /**
     * Get current status for list view with enhanced error handling.
     */
    public function listCurrentStatus()
    {
        try {
            $group = $this->getCurrentGroupId();
            $view_statuses = GroupStatuses::where('group_id', $group)
                ->where('type', 2)
                ->pluck('status_id');

            $status = Change_request_statuse::where('cr_id', $this->id)
                ->whereIn('new_status_id', $view_statuses)
                ->where('active', '1')
                ->first();

            return $status;
        } catch (Exception $e) {
            \Log::error("Error getting list current status for CR {$this->id}: " . $e->getMessage());

            return null;
        }
    }

    /**
     * Get current status (old method) with better error handling.
     */
    public function getCurrentStatusOld()
    {
        try {
            $status = Change_request_statuse::where('cr_id', $this->id)
                ->where('active', '1')
                ->first();

            if ($status) {
                $status = $this->attachWorkflowInfo($status);
            }

            return $status;
        } catch (Exception $e) {
            \Log::error("Error getting current status for CR {$this->id}: " . $e->getMessage());

            return null;
        }
    }

    /**
     * Get technical team current status with enhanced logic.
     */
    public function getTechnicalTeamCurrentStatus()
    {
        try {
            $group = $this->getCurrentGroupId();
            $technical_cr_team_status = null;

            $TechnicalCr = TechnicalCr::where('cr_id', $this->id)
                ->where('status', '0')
                ->first();

            if ($TechnicalCr) {
                $technical_cr_team_status = $TechnicalCr->technical_cr_team()
                    ->where('group_id', $group)
                    ->where('status', '0')
                    ->first();
            }

            return $technical_cr_team_status;
        } catch (Exception $e) {
            \Log::error("Error getting technical team status for CR {$this->id}: " . $e->getMessage());

            return null;
        }
    }

    /**
     * Get current status with enhanced workflow logic and better error handling.
     */
    public function getCurrentStatus()
    {
        try {
            if (request()->reference_status) {
                $statusInfo = Change_request_statuse::find(request()->reference_status);
                $status = $this->attachWorkflowInfo($statusInfo);

            } else {
                $group = $this->getCurrentGroupId();
                $view_statuses = GroupStatuses::where('group_id', $group)
                    ->where('type', 2)
                    ->pluck('status_id')
                    ->toArray();

                $technical_cr_team_status = $this->getTechnicalTeamCurrentStatus();

                if ($technical_cr_team_status) {
                    if (in_array($technical_cr_team_status->current_status_id, $view_statuses)) {
                        $view_statuses = [$technical_cr_team_status->current_status_id];
                    }
                }

                $status = Change_request_statuse::where('cr_id', $this->id)
                    ->whereIn('new_status_id', $view_statuses)
                    ->where('active', '1')
                    ->first();

                if ($status) {
                    $status = $this->attachWorkflowInfo($status);
                } else {
                    // Fallback logic
                    $status = Change_request_statuse::where('cr_id', $this->id)
                        ->where('active', '1')
                        ->first();

                    if ($status) {
                        $status = $this->attachWorkflowInfo($status);
                    } else {
                        $status = Change_request_statuse::where('cr_id', $this->id)
                            ->orderBy('id', 'desc')
                            ->first();
                        if ($status) {
                            $status = $this->attachWorkflowInfo($status);
                        }
                    }
                }
            }

            return $status;
        } catch (Exception $e) {
            \Log::error("Error getting current status for CR {$this->id}: " . $e->getMessage());

            return null;
        }
    }

    public function getAllCurrentStatus()
    {
        $statuses = Change_request_statuse::where('cr_id', $this->id)
            ->where('active', '1')
            ->orderBy('id', 'desc')
            ->get();
        
        // Group by the actual group being displayed and take the first (latest) from each group
        $grouped = $statuses->groupBy(function ($status) {
            // Create a unique key based on the actual group being displayed
            // Priority: reference_group_id > group_id > technical_group_id
            if ($status->reference_group_id) {
                return 'ref_' . $status->reference_group_id;
            } elseif ($status->group_id) {
                return 'group_' . $status->group_id;
            } elseif ($status->technical_group_id) {
                return 'tech_' . $status->technical_group_id;
            }
            return 'no_group';
        });
        
        // Take the first item from each group (which is the latest due to ordering)
        $deduplicated = $grouped->map(function ($group) {
            return $group->first();
        });
        
        // Return as a new collection to ensure proper collection behavior
        return new \Illuminate\Database\Eloquent\Collection($deduplicated->values()->all());
    }

    /**
     * Check if the change request is completed.
     */
    public function isCompleted(): bool
    {
        $currentStatus = $this->getCurrentStatus();

        if (!$currentStatus) {
            return false;
        }

        // Add your completed status IDs here
        $completedStatusIds = [/* Add your completed status IDs */];

        return in_array($currentStatus->new_status_id, $completedStatusIds);
    }

    /**
     * Check if change request needs approval.
     */
    public function needsApproval(): bool
    {
        return !$this->approval && $this->isInApprovalPhase();
    }

    /**
     * Get current status for division page with better error handling.
     */
    public function getCurrentStatusForDivision()
    {
        try {
            $status = Change_request_statuse::where('cr_id', $this->id)
                ->where('active', '1')
                ->first();

            if ($status) {
                $status = $this->attachWorkflowInfo($status);
            }

            return $status;
        } catch (Exception $e) {
            \Log::error("Error getting current status for CR {$this->id}: " . $e->getMessage());

            return null;
        }
    }

    public function inFinalState(): bool
    {
        $current_status = $this->currentRequestStatuses?->new_status_id;

        return in_array($current_status, [config('change_request.parked_status_ids.promo_closure')]);
    }

    public function isAlreadyCancelledOrRejected(): bool
    {
        $current_status = $this->currentRequestStatuses?->new_status_id;

        return in_array($current_status, [StatusConfigService::getStatusId('Reject'), StatusConfigService::getStatusId('Cancel')]);
    }

    public function getSetStatus()
    {
        $currentStatus = $this->getCurrentStatus();

        if (!$currentStatus) {
            return collect();
        }

        $statusId = $currentStatus->new_status_id;
        $previousStatusId = $currentStatus->old_status_id;

        return NewWorkFlow::where('from_status_id', $statusId)
            ->where(function ($query) use ($previousStatusId) {
                $query->whereNull('previous_status_id')
                    ->orWhere('previous_status_id', 0)
                    ->orWhere('previous_status_id', $previousStatusId);
            })
            ->whereHas('workflowstatus', function ($q) {
                $q->whereColumn('to_status_id', '!=', 'new_workflow.from_status_id');
            })
            ->where('type_id', $this->workflow_type_id)
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->orderBy('id', 'DESC')
            ->get();
    }

    /**
     * Get current group ID from session or user default.
     */
    private function getCurrentGroupId(): int
    {
        if (session('default_group')) {
            return session('default_group');
        }

        return auth()->user()->default_group ?? 1;
    }

    /**
     * Attach workflow information to status object.
     */
    private function attachWorkflowInfo($status)
    {
        if (!$status) {
            return null;
        }

        try {
            $workflow = NewWorkFlow::where('from_status_id', $status->old_status_id)
                ->where('type_id', $this->workflow_type_id)
                ->first();

            $status->same_time = $workflow->same_time ?? 0;
            $status->to_status_label = $workflow->to_status_label ?? '';

            return $status;
        } catch (Exception $e) {
            \Log::error("Error attaching workflow info for CR {$this->id}: " . $e->getMessage());
            $status->same_time = 0;
            $status->to_status_label = '';

            return $status;
        }
    }

    private function attachWorkflowInfoById($status)
    {
        if (!$status) {
            return null;
        }

        try {
            $workflow = NewWorkFlow::where('from_status_id', $status->new_status_id)
                ->where('type_id', $this->workflow_type_id)
                ->first();

            $status->same_time = $workflow->same_time ?? 0;
            $status->to_status_label = $workflow->to_status_label ?? '';

            return $status;
        } catch (Exception $e) {
            \Log::error("Error attaching workflow info for CR {$this->id}: " . $e->getMessage());
            $status->same_time = 0;
            $status->to_status_label = '';

            return $status;
        }
    }

    /**
     * Check if change request is in approval phase.
     */
    private function isInApprovalPhase(): bool
    {
        $currentStatus = $this->getCurrentStatus();

        if (!$currentStatus) {
            return false;
        }

        // Add your approval status IDs here
        $approvalStatusIds = [/* Add your approval status IDs */];

        return in_array($currentStatus->new_status_id, $approvalStatusIds);
    }
}
