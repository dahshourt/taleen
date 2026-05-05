<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Collection;

class Change_request extends Model
{
    use DynamicColumns;

    public const WORKFLOW_TYPE_CR = 3;

    public const STATUS_TYPE_ACTIVE = 1;

    /**
     * The table associated with the model.
     */
    public $table = 'change_request';

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'name',
        'is_overdue',
        'duration_summary',
        'completion_percentage',
        'ticket_type',
        'on_behalf_status',
        'assigned_technical_units',
    ];

    /**
     * Disable mass assignment protection for flexibility.
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'active' => 'boolean',
        'testable' => 'boolean',
        'top_management' => 'boolean',
        'need_design' => 'boolean',
        'need_iot_e2e_testing' => 'boolean',
        'need_down_time' => 'boolean',
        'postpone' => 'boolean',
        'approval' => 'boolean',
        'start_design_time' => 'datetime',
        'end_design_time' => 'datetime',
        'start_develop_time' => 'datetime',
        'end_develop_time' => 'datetime',
        'start_test_time' => 'datetime',
        'end_test_time' => 'datetime',
        'release_delivery_date' => 'datetime',
        'release_receiving_date' => 'datetime',
        'te_testing_date' => 'datetime',
        'uat_date' => 'datetime',
        'cost' => 'decimal:2',
        'design_duration' => 'integer',
        'develop_duration' => 'integer',
        'test_duration' => 'integer',
        'uat_duration' => 'integer',
        'man_days' => 'decimal:2',
    ];

    public static function getHoldRequests()
    {
        return self::where('hold', 1)->get();
    }

    // funtion to get the dependableCrs (CRs that can be dependable wich is all the crs that statuses is not delivered, closed, cancel or reject)
    // workflow_type_id = 3 (CR workflow type)
    // Excludes the current CR to prevent self-dependency
    public static function getDependableCrs(?int $excludeCrId = null)
    {
        // Get final status IDs from config (same as KPIRepository)
        $finalStatuses = [
            \App\Services\StatusConfigService::getStatusId('Delivered'),
            \App\Services\StatusConfigService::getStatusId('Closed'),
            \App\Services\StatusConfigService::getStatusId('Cancel'),
            \App\Services\StatusConfigService::getStatusId('Reject'),
        ];

        $targetWorkflowTypeId = 3;

        return self::where('workflow_type_id', $targetWorkflowTypeId)
            ->whereHas('currentStatusRel', function ($query) use ($finalStatuses) {
                $query->whereNotIn('new_status_id', $finalStatuses);
            })
            ->when($excludeCrId, function ($query) use ($excludeCrId) {
                $query->where('id', '!=', $excludeCrId);

                // to exclude CRs that already depend on this CR
                $dependentCrIds = CrDependency::where('depends_on_cr_id', $excludeCrId)->where('status', '0')
                    ->pluck('cr_id')
                    ->toArray();

                if (!empty($dependentCrIds)) {
                    $query->whereNotIn('id', $dependentCrIds);
                }

                return $query;
            })
            ->orderBy('cr_no', 'desc')
            ->get(['id', 'cr_no', 'title']);
    }

    public function scopeNotInFinalState(Builder $query): Builder
    {
        return $query->whereHas('currentRequestStatuses', function ($query) {
            return $query->whereNotIn('new_status_id', [\App\Services\StatusConfigService::getStatusId('Reject'), \App\Services\StatusConfigService::getStatusId('Cancel'), config('change_request.parked_status_ids.promo_closure')]);
        });
    }

    // ===================================
    // RELATIONSHIPS
    // ===================================

    /**
     * Get the defects associated with this change request.
     */
    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class, 'cr_id', 'id');
    }

    /**
     * Get the logs associated with this change request.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(Log::class, 'cr_id', 'id');
    }

    public function cabCrs(): HasMany
    {
        return $this->hasMany(CabCr::class, 'cr_id', 'id');
    }

    public function srBusinessApprovals(): HasMany
    {
        return $this->hasMany(SrBusinessApprovalCr::class, 'cr_id', 'id');
    }

    /**
     * Get only active (status = 0) cab_crs records
     */
    public function activeCabCrs(): HasMany
    {
        return $this->hasMany(CabCr::class, 'cr_id', 'id')
            ->where('status', '0');
    }

    /**
     * Get the custom fields for this change request.
     */
    public function changeRequestCustomFields(): HasMany
    {
        return $this->hasMany(ChangeRequestCustomField::class, 'cr_id', 'id');
    }

    /**
     * Get request statuses for this change request.
     */
    public function reqStatus(): HasMany
    {
        return $this->hasMany(Change_request_statuse::class, 'cr_id', 'id')
            ->select('id', 'new_status_id', 'old_status_id', 'active');
    }

    /**
     * Get the category this change request belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id')->select('id', 'name');
    }

    /**
     * Get the priority of this change request.
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priority_id')->select('id', 'name');
    }

    /**
     * Get the department this change request belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id')->select('id', 'name');
    }

    /**
     * Get the application this change request is for.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id')->select('id', 'name', 'app_support', 'operation_dm');
    }

    /**
     * Get the change request this one depends on.
     */
    public function dependCr(): BelongsTo
    {
        return $this->belongsTo(Change_request::class, 'depend_cr_id')->select('id', 'title', 'cr_no');
    }

    /**
     * Get the requester of this change request.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the developer assigned to this change request.
     */
    public function developer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'developer_id')->select('id', 'name', 'user_name', 'email');
    }

    /**
     * Get the tester assigned to this change request.
     */
    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tester_id')->select('id', 'name', 'user_name', 'email');
    }

    /**
     * Get the designer assigned to this change request.
     */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id')->select('id', 'name', 'user_name', 'email');
    }

    public function kpis(): BelongsToMany
    {
        return $this->belongsToMany(Kpi::class, 'kpi_change_request', 'cr_id', 'kpi_id');
    }

    /**
     * Get the active CAB record for this change request.
     */
    public function cabCr(): HasOne
    {
        return $this->hasOne(CabCr::class, 'cr_id', 'id')->where('status', '0');
    }

    /**
     * Get the active technical CR record.
     */
    public function technicalCr(): HasOne
    {
        return $this->hasOne(TechnicalCr::class, 'cr_id', 'id')->where('status', '0');
    }

    /**
     * Get the first technical CR record.
     */
    public function technicalCrFirst(): HasOne
    {
        return $this->hasOne(TechnicalCr::class, 'cr_id', 'id')->orderBy('id', 'DESC');
    }

    /**
     * Get the active SR technical CR record.
     */
    public function srTechnicalCr(): HasOne
    {
        return $this->hasOne(SrTechnicalCr::class, 'cr_id', 'id')->where('status', '0');
    }

    /**
     * Get the first SR technical CR record.
     */
    public function srTechnicalCrFirst(): HasOne
    {
        return $this->hasOne(SrTechnicalCr::class, 'cr_id', 'id')->orderBy('id', 'DESC');
    }

    /**
     * Get assigned technical units for this change request.
     */
    public function assignedTechnicalUnits()
    {
        return Group::join('technical_cr_teams', 'technical_cr_teams.group_id', '=', 'groups.id')
            ->join('technical_crs', 'technical_crs.id', '=', 'technical_cr_teams.technical_cr_id')
            ->where('technical_crs.cr_id', $this->id)
            ->where('technical_crs.status', TechnicalCr::INACTIVE)
            ->where('technical_cr_teams.status', TechnicalCrTeam::APPROVED)
            ->select('groups.*')
            ->get();
    }

    /**
     * Get assigned technical unit names as a comma-separated string.
     */
    public function getAssignedTechnicalUnitsAttribute()
    {
        $units = $this->assignedTechnicalUnits();
        return $units->isNotEmpty() ? $units->pluck('name')->implode(', ') : 'N/A';
    }

    /**
     * Get current group statuses through status relationship.
     */
    public function currentStatus(): HasManyThrough
    {
        return $this->hasManyThrough(
            GroupStatuses::class,
            Change_request_statuse::class,
            'cr_id',
            'status_id',
            'id',
            'new_status_id'
        )->where('group_statuses.type', 1);
    }

    /**
     * Get request statuses ordered by ID descending.
     */
    public function requestStatuses(): HasMany
    {
        return $this->hasMany(Change_request_statuse::class, 'cr_id', 'id')
            ->where('active', '1')
            ->orderBy('id', 'DESC');
    }

    public function requestStatusesDone(): HasMany
    {
        return $this->hasMany(Change_request_statuse::class, 'cr_id', 'id')
            ->where('active', '2')
            ->orderBy('id', 'desc');
    }

    public function allRequestStatuses(): HasMany
    {
        return $this->hasMany(Change_request_statuse::class, 'cr_id', 'id')->orderBy('id', 'DESC');
    }

    /**
     * Get the current request status.
     */
    public function currentRequestStatuses(): HasOne
    {
        return $this->hasOne(Change_request_statuse::class, 'cr_id', 'id')->where('active', '1');
    }

    public function currentRequestStatusesLast(): HasOne
    {
        return $this->hasOne(Change_request_statuse::class, 'cr_id', 'id')->whereIn('active', [1, 2]);
    }

    /**
     * Get the division manager for this change request.
     */
    public function divisionManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_manager_id');
    }

    /**
     * Get attachments for this change request.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachements_crs::class, 'cr_id');
    }

    public function businessAttachments(): HasMany
    {
        return $this->hasMany(Attachements_crs::class, 'cr_id')
            ->where('flag', 2);
    }

    /**
     * Get the release this change request is associated with.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'release_name', 'id');
    }

    /**
     * Get the workflow type this change request belongs to.
     */
    public function workflowType(): BelongsTo
    {
        return $this->belongsTo(WorkFlowType::class, 'workflow_type_id')->select('id', 'name');
    }

    /**
     * Get current status with relationship.
     */
    public function currentStatusRel(): HasOne
    {
        return $this->hasOne(Change_request_statuse::class, 'cr_id')
            ->where('active', '1')
            ->latest('id')
            ->with('status');
    }

    public function crHold(): HasOne
    {
        return $this->hasOne(ChangeRequestHold::class)->latest('id');
    }

    // ===================================
    // ACCESSORS & MUTATORS
    // ===================================

    /**
     * Get the name attribute (alias for title).
     */
    public function getNameAttribute(): string
    {
        return $this->title ?? '';
    }

    /**
     * Check if the change request is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        $releaseDate = $this->release_delivery_date;

        return $releaseDate && $releaseDate->isPast() && !$this->isCompleted();
    }

    /**
     * Get a summary of all durations.
     */
    public function getDurationSummaryAttribute(): array
    {
        return [
            'design' => $this->design_duration ?? 0,
            'development' => $this->develop_duration ?? 0,
            'testing' => $this->test_duration ?? 0,
            'uat' => $this->uat_duration ?? 0,
            'total' => $this->getTotalDuration(),
        ];
    }

    /**
     * Calculate completion percentage based on completed phases.
     */
    public function getCompletionPercentageAttribute(): int
    {
        $phases = ['design', 'development', 'testing'];
        $completedPhases = 0;
        $totalPhases = count($phases);

        if ($this->end_design_time) {
            $completedPhases++;
        }
        if ($this->end_develop_time) {
            $completedPhases++;
        }
        if ($this->end_test_time) {
            $completedPhases++;
        }

        return $totalPhases > 0 ? round(($completedPhases / $totalPhases) * 100) : 0;
    }

    public function getTicketTypeAttribute()
    {
        $field = $this->changeRequestCustomFields->where('custom_field_name', 'cr_type')->first();
        $val = $field ? $field->custom_field_value : null;

        if ($val == 1) {
            return 'Normal';
        }
        if ($val == 2) {
            return 'Depend On';
        }
        if ($val == 3) {
            return 'Relevant';
        }

        return 'Normal';
    }

    public function getOnBehalfStatusAttribute()
    {
        $field = $this->changeRequestCustomFields->where('custom_field_name', 'on_behalf')->first();

        return ($field && $field->custom_field_value == '1') ? 'YES' : 'N/A';
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeFilters(Builder $query): Builder
    {
        return $query
            ->filterByBasicFields()
            ->filterByRelations()
            ->filterByDates()
            ->filterByStatus()
            ->filterByCustomFields();
    }

    public function scopeFilterByBasicFields(Builder $query): Builder
    {
        return $query
            ->when(request()->query('cr_no'), fn(Builder $q, $value) => $q->where('cr_no', $value))
            ->when(request()->query('title'), fn(Builder $q, $value) => $q->where('title', 'like', "%{$value}%"));
    }

    public function scopeFilterByRelations(Builder $query): Builder
    {
        return $query
            ->when(request()->query('application_id'), fn(Builder $q, $value) => $q->whereIn('application_id', (array) $value))
            ->when(request()->query('tester_id'), fn(Builder $q, $value) => $q->whereIn('tester_id', (array) $value))
            ->when(request()->query('developer_id'), fn(Builder $q, $value) => $q->whereIn('developer_id', (array) $value))
            ->when(request()->query('designer_id'), fn(Builder $q, $value) => $q->whereIn('designer_id', (array) $value))
            ->when(request()->query('category_id'), fn(Builder $q, $value) => $q->whereIn('category_id', (array) $value))
            ->when(request()->query('priority_id'), fn(Builder $q, $value) => $q->whereIn('priority_id', (array) $value))
            ->when(request()->query('unit_id'), fn(Builder $q, $value) => $q->whereIn('unit_id', (array) $value))
            ->when(request()->query('division_manager'), fn(Builder $q, $value) => $q->where('division_manager', $value))
            ->when(request()->query('workflow_type_id'), fn(Builder $q, $value) => $q->whereIn('workflow_type_id', (array) $value))
            ->when(request()->query('requester_name'), fn(Builder $q, $value) => $q->where('requester_name', 'like', "%{$value}%"));
    }

    public function scopeFilterByDates(Builder $query): Builder
    {
        return $query
            ->when(request()->query('created_at_start'), fn(Builder $q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when(request()->query('created_at_end'), fn(Builder $q, $value) => $q->whereDate('created_at', '<=', $value))
            ->when(request()->query('updated_at_start'), fn(Builder $q, $value) => $q->whereDate('updated_at', '>=', $value))
            ->when(request()->query('updated_at_end'), fn(Builder $q, $value) => $q->whereDate('updated_at', '<=', $value));
    }

    public function scopeFilterByStatus(Builder $query): Builder
    {
        return $query->when(request()->query('new_status_id'), function (Builder $q, $value) {
            $q->whereHas('currentRequestStatuses', function (Builder $subQ) use ($value) {
                $subQ->whereIn('new_status_id', (array) $value);
            });
        });
    }

    public function scopeFilterByCustomFields(Builder $query): Builder
    {
        return $query
            ->when(request()->query('cr_type'), function (Builder $q, $value) {
                $q->whereHas('changeRequestCustomFields', function ($subQ) use ($value) {
                    $subQ->where('custom_field_name', 'cr_type')
                        ->whereIn('custom_field_value', (array) $value);
                });
            })
            ->when(request()->query('on_behalf'), function (Builder $q, $value) {
                if ($value) {
                    $q->whereHas('changeRequestCustomFields', function ($subQ) {
                        $subQ->where('custom_field_name', 'on_behalf')
                            ->where('custom_field_value', '1');
                    });
                }
            });
    }

    /**
     * Scope a query to only include active change requests.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope a query to only include change requests by priority.
     */
    public function scopeByPriority(Builder $query, int $priorityId): Builder
    {
        return $query->where('priority_id', $priorityId);
    }

    /**
     * Scope a query to only include change requests by category.
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to only include change requests assigned to a user.
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('developer_id', $userId)
                ->orWhere('tester_id', $userId)
                ->orWhere('designer_id', $userId);
        });
    }

    /**
     * Scope a query to only include overdue change requests.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('release_delivery_date', '<', now())
            ->where('active', true);
    }

    // ===================================
    // ORIGINAL METHODS - ENHANCED
    // ===================================

    /**
     * Get current status for list view with enhanced error handling.
     */
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
     * Get available releases with enhanced filtering.
     */
    /**
     * Get available releases with enhanced filtering.
     */
    public function getReleases(): Collection
    {
        return Release::all();
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
                $workflow = NewWorkFlow::where('from_status_id', $status->old_status_id)
                    ->where('type_id', $this->workflow_type_id)
                    ->first();

                $status->same_time = $workflow->same_time ?? 0;
                $status->to_status_label = $workflow->to_status_label ?? '';
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
    /**
     * Get technical team current status with enhanced logic.
     */
    public function getTechnicalTeamCurrentStatus()
    {
        try {
            $group = $this->getCurrentGroupId();
            $technical_cr_team_status = null;

            $TechnicalCr = $this->technicalCr;

            if ($TechnicalCr && $TechnicalCr->status == '0') {
                $technical_cr_team_status = $TechnicalCr->technicalCRTeams
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
            $status = $this->resolveCurrentStatus();

            return $this->attachWorkflowInfo($status);
        } catch (Exception $e) {
            \Log::error("Error getting current status for CR {$this->id}: " . $e->getMessage());

            return null;
        }
    }

    public function scopeWithAllCRStatusesInfo(Builder $query): Builder
    {
        return $query->with([
            'requestStatuses' => function (HasMany $query) {
                $query->with([
                    'currentGroup',
                    'technical_group',
                    'referenceGroup',
                    'previousGroup',
                    'status' => function ($query) {
                        $query->with('viewByGroupStatuses.group');
                    },
                ]);
            }
        ]);
    }

    public function getAllCurrentStatus()
    {
        $statuses = Change_request_statuse::where('cr_id', $this->id)
            ->where('active', '1')
            ->with([
                'currentGroup',
                'technical_group',
                'referenceGroup',
                'previousGroup',
                'status' => function ($query) {
                    $query->with('viewByGroupStatuses.group');
                },
            ])
            ->get();

        return $statuses;
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
     * Get total duration of all phases.
     */
    public function getTotalDuration(): int
    {
        return ($this->design_duration ?? 0) +
            ($this->develop_duration ?? 0) +
            ($this->test_duration ?? 0) +
            ($this->uat_duration ?? 0);
    }

    /**
     * Get remaining duration based on current phase.
     */
    public function getRemainingDuration(): int
    {
        $totalDuration = $this->getTotalDuration();
        $completionPercentage = $this->completion_percentage;

        return max(0, $totalDuration - (($completionPercentage / 100) * $totalDuration));
    }

    /**
     * Check if the change request is editable by a given user.
     *
     * @param array $crsInQueues IDs of CRs currently in the user's queues.
     * @param int|null $userId ID of the user (defaults to current authenticated user).
     * @return bool
     */
    public function isEditableByUser(array $crsInQueues = [], ?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        $isRequesterEditState = $this->isRequesterEditState($userId);
        $isInQueues = in_array($this->id, $crsInQueues);

        // Visibility condition
        if (!($isInQueues || $isRequesterEditState)) {
            return false;
        }

        // Workflow 5 specific restriction
        if ($this->workflow_type_id == 5) {
            $latestStatusId = $this->Req_status()->latest('id')->first()?->new_status_id;
            if (in_array($latestStatusId, [66, 67, 68, 69])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the change request is in a state where the requester can edit it.
     *
     * @param int|null $userId ID of the user (defaults to current authenticated user).
     * @return bool
     */
    public function isRequesterEditState(?int $userId = null): bool
    {
        $userId = $userId ?? auth()->id();

        if ($this->requester_id != $userId) {
            return false;
        }

        $status = $this->getCurrentStatus()?->status;
        $statusName = trim($status?->status_name ?? '');

        $allowedStatuses = [
            'Pending Business Confirmation',
            'SR Business FB',
        ];

        return in_array(strtolower($statusName), array_map('strtolower', $allowedStatuses));
    }

    /**
     * Check if change request needs approval.
     */
    public function needsApproval(): bool
    {
        return !$this->approval && $this->isInApprovalPhase();
    }

    /**
     * Get change requests that depend on this one.
     */
    public function getDependentChangeRequests(): Collection
    {
        return self::where('depend_cr_id', $this->id)
            ->where('active', true)
            ->get();
    }

    /**
     * Check if this change request has dependencies that are not completed.
     */
    public function hasUncompletedDependencies(): bool
    {
        if (!$this->depend_cr_id) {
            return false;
        }

        $dependentCr = self::find($this->depend_cr_id);

        return $dependentCr && !$dependentCr->isCompleted();
    }

    /**
     * Get estimated completion date based on current progress.
     */
    public function getEstimatedCompletionDate(): ?\Carbon\Carbon
    {
        if ($this->isCompleted()) {
            return $this->updated_at;
        }

        $remainingDays = $this->getRemainingDuration();

        if ($remainingDays <= 0) {
            return now();
        }

        return now()->addDays($remainingDays);
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
                $workflow = NewWorkFlow::where('from_status_id', $status->old_status_id)
                    ->where('type_id', $this->workflow_type_id)
                    ->first();

                $status->same_time = $workflow->same_time ?? 0;
                $status->to_status_label = $workflow->to_status_label ?? '';
            }

            return $status;
        } catch (Exception $e) {
            \Log::error("Error getting current status for CR {$this->id}: " . $e->getMessage());

            return null;
        }
    }

    public function inFinalState(): bool
    {
        $current_status = $this->currentRequestStatuses->new_status_id;

        return in_array($current_status, [config('change_request.parked_status_ids.promo_closure')]);
    }

    public function isAlreadyCancelledOrRejected(): bool
    {
        $current_status = $this->currentRequestStatuses->new_status_id;

        return in_array($current_status, [\App\Services\StatusConfigService::getStatusId('Reject'), \App\Services\StatusConfigService::getStatusId('Cancel')]);
    }

    public function resDeveloper()
    {
        return $this->hasOne(CrAssignee::class, 'cr_id', 'id')->where('role', 'developer')->latest();
    }

    public function resTester()
    {
        return $this->hasOne(CrAssignee::class, 'cr_id', 'id')->where('role', 'tester')->latest();
    }

    public function resDesigner()
    {
        return $this->hasOne(CrAssignee::class, 'cr_id', 'id')->where('role', 'designer')->latest();
    }

    public function resCrMember()
    {
        return $this->hasOne(CrAssignee::class, 'cr_id', 'id')->where('role', 'cr_member')->latest();
    }

    public function getNameColumn()
    {
        return 'cr_no';
    }

    public function isOnHold(): bool
    {
        return $this->hold === 1;
    }

    public function isOnGoing(): bool
    {
        return (bool) $this->parent_id;
    }

    public function getSetStatus(): Collection
    {
        $currentStatus = $this->getCurrentStatus();

        $statusId = $currentStatus->new_status_id;
        $previousStatusId = $currentStatus->old_status_id;

        return NewWorkFlow::where('from_status_id', $statusId)
            ->where(function ($query) {
                $query->whereNull('previous_status_id')
                    ->orWhere('previous_status_id', 0)
                    ->orWhere('previous_status_id', '>', 0);
            })
            ->whereHas('workflowstatus', function ($q) {
                $q->whereColumn('to_status_id', '!=', 'new_workflow.from_status_id');
            })
            ->where('type_id', $this->workflow_type_id)
            ->whereRaw('CAST(active AS CHAR) = ?', ['1'])
            ->orderBy('id', 'DESC')
            ->get();
    }

    public function member(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            ChangeRequestCustomField::class,
            'cr_id',
            'id',
            'id',
            'custom_field_value'
        )->where('change_request_custom_fields.custom_field_name', 'cr_member');
    }

    public function requesterDepartment(): HasOneThrough
    {
        return $this->hasOneThrough(
            RequesterDepartment::class,      // Final model
            ChangeRequestCustomField::class, // Intermediate model
            'cr_id',                         // FK on change_request_custom_fields → change_requests.id
            'id',                            // PK on requester_departments
            'id',                            // PK on change_requests
            'custom_field_value'             // FK on change_request_custom_fields → requester_departments.id
        )->where(
                'change_request_custom_fields.custom_field_name',
                'requester_department'
            );
    }

    public function parentCR(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function rejectionReason(): HasOneThrough
    {
        return $this->hasOneThrough(
            RejectionReasons::class,
            ChangeRequestCustomField::class,
            'cr_id',
            'id',
            'id',
            'custom_field_value'
        )->where('change_request_custom_fields.custom_field_name', 'rejection_reason_id');
    }

    public function accumulativeMDs(): HasOne
    {
        return $this->hasOne(ChangeRequestCustomField::class, 'cr_id')
            ->where('change_request_custom_fields.custom_field_name', 'accumulative_mds');
    }

    public function deploymentDate(): HasOne
    {
        return $this->hasOne(ChangeRequestCustomField::class, 'cr_id')
            ->where('change_request_custom_fields.custom_field_name', 'deployment_date');
    }

    // funtion to check if the cr waiting for other CRs to be delivered
    public function isDependencyHold(): bool
    {
        return $this->is_dependency_hold === true || $this->is_dependency_hold === 1;
    }

    // funtion to get the CR numbers that are blocking this CR from cr_dependencies table
    public function getBlockingCrNumbers(): array
    {
        return $this->activeDependencies()
            ->select('cr_no')
            ->pluck('cr_no')
            ->toArray();
    }

    // function to get the CRs that this CR depends on (multi-CR dependency via cr_dependencies table)
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'cr_dependencies',
            'cr_id',
            'depends_on_cr_id'
        )->withPivot('status')->withTimestamps();
    }

    // funtion to get the CRs that depend on this CR
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'cr_dependencies',
            'depends_on_cr_id',
            'cr_id'
        )->withPivot('status')->withTimestamps();
    }

    // funtion to get only active (unresolved) dependencies
    public function activeDependencies(): BelongsToMany
    {
        return $this->dependencies()->wherePivot('status', '0');
    }

    // funtion to check if this CR has any unresolved dependencies
    public function hasActiveDependencies(): bool
    {
        return $this->activeDependencies()->exists();
    }

    /**
     * Check if the change request should be shown to the user based on technical team flags.
     *
     * @param  int  $defaultGroup
     */
    public function shouldShowToUser($defaultGroup): bool
    {
        $currentStatus = $this->getCurrentStatus();
        if (!$currentStatus || !$currentStatus->status) {
            return false;
        }

        $viewTechnicalTeamFlag = $currentStatus->status->view_technical_team_flag;
        // For SR Workflow (type 15), use view_sr_technical_team_flag instead
        if ($this->workflow_type_id == 15) {
            $viewTechnicalTeamFlag = $currentStatus->status->view_sr_technical_team_flag;
        }

        if (!$viewTechnicalTeamFlag) {
            return true;
        }

        // Use appropriate technical record based on workflow type
        $techRecord = $this->workflow_type_id == 15 ? $this->srTechnicalCr : $this->technicalCr;

        $assignedTechnicalTeams = $techRecord
            ? $techRecord->technical_cr_team->pluck('group_id')->toArray()
            : [];

        $checkIfStatusActive = $techRecord
            ? $techRecord->technical_cr_team
                ->where('group_id', $defaultGroup)
                ->where('status', '0')
                ->count()
            : 0;

        return in_array($defaultGroup, $assignedTechnicalTeams) && $checkIfStatusActive;
    }

    /**
     * Generate a token for approval/rejection actions.
     */
    public function generateActionToken(): string
    {
        return md5($this->id . $this->created_at . env('APP_KEY'));
    }

    public function getCrTypeNameAttribute(): string
    {
        static $crTypes;
        if (!$crTypes) {
            $crTypes = \App\Models\CrType::pluck('name', 'id');
        }

        $crTypeField = $this->changeRequestCustomFields
            ->where('custom_field_name', 'cr_type')
            ->first();

        return $crTypeField ? ($crTypes[$crTypeField->custom_field_value] ?? '') : '';
    }

    public function getOnBehalfAttribute(): string
    {
        $onBehalf = $this->changeRequestCustomFields
            ->where('custom_field_name', 'on_behalf')
            ->where('custom_field_value', '1')
            ->first();

        return $onBehalf ? 'Yes' : 'No';
    }

    // ===================================
    // DEPRECATED METHODS
    // ===================================

    /**
     * @deprecated Use changeRequestCustomFields() instead.
     */
    public function change_request_custom_fields(): HasMany
    {
        return $this->changeRequestCustomFields();
    }

    /**
     * @deprecated Use reqStatus() instead.
     */
    public function Req_status(): HasMany
    {
        return $this->reqStatus();
    }

    /**
     * @deprecated Use dependCr() instead.
     */
    public function depend_cr(): BelongsTo
    {
        return $this->dependCr();
    }

    /**
     * @deprecated Use cabCr() instead.
     */
    public function cab_cr(): HasOne
    {
        return $this->cabCr();
    }

    /**
     * Get the active Director Approval record for this change request.
     */
    public function directorApprovalCr(): HasOne
    {
        return $this->hasOne(DirectorApprovalCr::class, 'cr_id', 'id')->where('status', '0');
    }

    public function directorApprovalCrs(): HasMany
    {
        return $this->hasMany(DirectorApprovalCr::class, 'cr_id', 'id');
    }

    /**
     * @deprecated Use technicalCr() instead.
     */
    public function technical_Cr(): HasOne
    {
        return $this->technicalCr();
    }

    /**
     * @deprecated Use technicalCrFirst() instead.
     */
    public function technical_Cr_first(): HasOne
    {
        return $this->technicalCrFirst();
    }

    /**
     * @deprecated Use currentStatus() instead.
     */
    public function current_status(): HasManyThrough
    {
        return $this->currentStatus();
    }

    /**
     * @deprecated Use currentRequestStatusesLast() instead.
     */
    public function CurrentRequestStatuses_last(): HasOne
    {
        return $this->currentRequestStatusesLast();
    }

    /**
     * @deprecated Use divisionManager() instead.
     */
    public function division_manger(): BelongsTo
    {
        return $this->divisionManager();
    }

    /**
     * @deprecated Use getReleases() instead.
     */
    public function get_releases(): Collection
    {
        return $this->getReleases();
    }

    private function resolveCurrentStatus()
    {
        if (request()->reference_status) {
            return Change_request_statuse::find(request()->reference_status);
        }

        $viewStatuses = $this->getViewableStatuses();

        $status = $this->requestStatuses->where('cr_id', $this->id)
            ->whereIn('new_status_id', $viewStatuses)
            ->where('active', '1') // Ensure we only look for active statuses if that was the intent
            ->first();

        if ($status) {
            return $status;
        }

        // Fallback 1: Active status
        $status = $this->requestStatuses->where('cr_id', $this->id)
            ->where('active', '1')
            ->first();

        if ($status) {
            return $status;
        }

        // Fallback 2: Latest status (Normal CR)
        $status = $this->requestStatuses->where('cr_id', $this->id)
            ->sortByDesc('id')
            ->first();

        if ($status) {
            return $status;
        }

        // Fallback 3: ON Hold Case
        return $this->allRequestStatuses()
            ->first();
    }

    private function getViewableStatuses(): array
    {
        $group = $this->getCurrentGroupId();

        if (!app()->bound("group_{$group}_statuses")) {
            $group_statuses = GroupStatuses::where('group_id', $group)
                ->where('type', 2)
                ->pluck('status_id')
                ->toArray();

            app()->instance("group_{$group}_statuses", $group_statuses);
        }

        $viewStatuses = app("group_{$group}_statuses");

        $technicalTeamStatus = $this->getTechnicalTeamCurrentStatus();

        if ($technicalTeamStatus && in_array($technicalTeamStatus->current_status_id, $viewStatuses)) {
            return [$technicalTeamStatus->current_status_id];
        }

        return $viewStatuses;
    }

    // ===================================
    // HELPER METHODS
    // ===================================

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
            // Try to use preloaded workflows first
            $preloadedWorkflows = app()->has('preloaded_workflows') ? app('preloaded_workflows') : null;

            if ($preloadedWorkflows) {
                $key = $this->workflow_type_id . '_' . $status->old_status_id;
                $workflow = $preloadedWorkflows->get($key)?->first();
            } else {
                // Fallback to database query if not preloaded
                $workflow = NewWorkFlow::where('from_status_id', $status->old_status_id)
                    ->where('type_id', $this->workflow_type_id)
                    ->first();
            }

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
            // Try to use preloaded workflows first
            $preloadedWorkflows = app()->has('preloaded_workflows') ? app('preloaded_workflows') : null;

            if ($preloadedWorkflows) {
                $key = $this->workflow_type_id . '_' . $status->new_status_id;
                $workflow = $preloadedWorkflows->get($key)?->first();
            } else {
                // Fallback to database query if not preloaded
                $workflow = NewWorkFlow::where('from_status_id', $status->new_status_id)
                    ->where('type_id', $this->workflow_type_id)
                    ->first();
            }

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
