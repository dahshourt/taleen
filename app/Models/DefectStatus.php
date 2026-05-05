<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefectStatus extends Model
{
    use HasFactory;

    public $table = 'defect_statuses';

    protected $fillable = [
        'defect_id',
        'user_id',
        'previous_status_id',
        'new_status_id',
    ];

    public function defect()
    {
        return $this->belongsTo(Defect::class, 'defect_id');

    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');

    }

    public function current_status()
    {
        return $this->belongsTo(Status::class, 'new_status_id');

    }

    public function previous_statu()
    {
        return $this->belongsTo(Status::class, 'previous_status_id');

    }
}
