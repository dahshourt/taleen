<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DependWorkflow extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'depend_workflow_status';

    protected $fillable = [
        'to_status_id', 'depend_status_id', 'active',

    ];

    public function depend_workflow_from_status()
    {
        return $this->belongsTo(Status::class, 'to_status_id')->select('id', 'status_name');

    }

    public function depend_workflow_depend_status()
    {
        return $this->belongsTo(Status::class, 'depend_status_id')->select('id', 'status_name');

    }
}
