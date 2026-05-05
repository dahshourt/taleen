<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupApplications extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'group_id',
    ];

    public function application()
    {
        return $this->belongsTo(application::class);
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
