<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupStatuses extends Model
{
    use HasFactory;

    /**
     * The code of group type set by.
     *
     * @var string
     */
    const SETBY = 1;

    /**
     * The code of group type view by.
     *
     * @var string
     */
    const VIEWBY = 2;

    protected $fillable = [
        'status_id',
        'group_id',
        'type',
    ];

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    // public function view_group()
    // {
    //     return $this->belongsTo(Group::class);
    // }

}
