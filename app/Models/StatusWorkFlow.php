<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusWorkFlow extends Model
{
    use HasFactory;

    protected $table = 'status_work_flow';

    protected $fillable = [
        'from_status_id',
        'to_status_id',
        'from_stage_id',
        'to_stage_id',
        'type',
    ];

    public function from_status()
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function to_status()
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }

    public function from_stage()
    {
        return $this->belongsTo(Stage::class, 'from_stage_id');
    }

    public function to_stage()
    {
        return $this->belongsTo(Stage::class, 'to_stage_id');
    }
}
