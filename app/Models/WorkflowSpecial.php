<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowSpecial extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $table = 'workflow_special';

    protected $fillable = [
        'no_need_desgin', 'not_testable', 'workflow_type_id', 'from_status_id', 'to_workflow_id',
    ];
}
