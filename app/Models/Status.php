<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    use DynamicColumns;

    protected $table = 'statuses';

    protected $with = ['high_level'];

    protected $appends = ['name'];

    /**
     * The attributes that are mass assignable. test
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'workflow_type_id',
        'status_name',
        'stage_id',
        'sla',
        'active',
        'view_technical_team_flag',
        'view_sr_technical_team_flag',
        'log_message',
    ];

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', '1');
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function group_statuses()
    {
        return $this->hasMany(GroupStatuses::class);
    }

    public function setByGroupStatuses(): HasMany
    {
        return $this->group_statuses()
            ->where('type', GroupStatuses::SETBY);
    }

    public function viewByGroupStatuses(): HasMany
    {
        return $this->group_statuses()
            ->where('type', GroupStatuses::VIEWBY);
    }

    public function from_status()
    {
        return $this->hasMany(StatusWorkFlow::class, 'from_status_id');
    }

    public function to_status()
    {
        return $this->hasMany(StatusWorkFlow::class, 'to_status_id');
    }

    public function to_status_workflow()
    {
        return $this->hasMany(Workflow::class, 'from_status_id');
    }

    public function high_level()
    {
        return $this->belongsTo(HighLevelStatuses::class, 'high_level_status_id')->where('active', '1');
    }

    public function getNameAttribute()
    {
        return $this->status_name;
    }

    public function workflow_type()
    {
        return $this->belongsTo(WorkFlowType::class, 'workflow_type_id');
    }

    public function GetViewGroup($application_id = null)
    {

        if ($application_id) {
            $group = $this->viewByGroupStatuses()->whereHas('group.group_applications', function ($q) use ($application_id) {
                $q->where('application_id', $application_id);
            })->first();

            if ($group) {
                return $group->group;
            }
        }

        // Fallback to first group (using property to leverage eager loading if available, else lazy load)
        $firstStatus = $this->viewByGroupStatuses->first();

        return $firstStatus ? $firstStatus->group : null;
    }
}
