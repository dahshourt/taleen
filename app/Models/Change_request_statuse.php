<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Change_request_statuse extends Model
{
    use HasFactory;

    public $table = 'change_request_statuses';

    protected $fillable = [
        'cr_id',
        'old_status_id',
        'new_status_id',
        'user_id',
        'group_id',
        'active',
        'sla',
        'sla_dif',
        'assignment_user_id',
        'reference_group_id',
        'previous_group_id',
        'current_group_id',

    ];

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    public function CR()
    {
        return $this->belongs_to(change_request::class, 'cr_id'); // how is it work "belongs_to" ? the right is "belongsTo"

    }

    public function change_request_data()
    {
        return $this->belongsTo(change_request::class, 'cr_id', 'id');

    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }

    public function current_status_for_group()
    {
        return $this->belongsTo(GroupStatuses::class, 'new_status_id', 'status_id');
    }

    // mahmoud's update
    public function ChangeRequest()
    {
        return $this->belongsTo(change_request::class, 'cr_id');
    }

    public function wokflow()
    {
        return $this->belongsTo(NewWorkFlow::class, 'new_status_id', 'from_status_id');
    }

    public function technical_group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function referenceGroup()
    {
        return $this->belongsTo(Group::class, 'reference_group_id');
    }

    public function previousGroup()
    {
        return $this->belongsTo(Group::class, 'previous_group_id');
    }

    public function currentGroup()
    {
        return $this->belongsTo(Group::class, 'current_group_id');
    }

    public function scopeActive($query)
    {
        return $query->whereRaw('CAST(active AS CHAR) = ?', ['1']);
    }

    public function scopeNotActive($query)
    {
        return $query->whereRaw('CAST(active AS CHAR) = ?', ['0']);
    }

    public function scopeCompleted($query)
    {
        return $query->whereRaw('CAST(active AS CHAR) = ?', ['2']);
    }

    public function scopeCompletedOrActive($query)
    {
        return $query->whereRaw('CAST(active AS CHAR) != ?', ['0']);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->updated_at = null;
        });
    }
}
