<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Group extends Model
{
    use DynamicColumns, HasRoles;

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'active',
        'parent_id',
        'head_group_name',
        'head_group_email',
        'man_power',
        'active',
        'technical_team',
        'sr_technical_team',
        'recieve_notification',
    ];

    protected $appends = ['name'];

    public function user_groups()
    {
        return $this->hasMany(UserGroups::class);
    }

    public function group_statuses()
    {
        return $this->hasMany(GroupStatuses::class);
    }

    public function group_applications()
    {
        return $this->hasMany(GroupApplications::class);
    }

    public function children()
    {
        return $this->hasMany(Group::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Group::class, 'parent_id');
    }

    public function getNameAttribute()
    {
        return $this->title;
    }
}
