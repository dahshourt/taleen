<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaCalculation extends Model
{
    use HasFactory;

    protected $table = 'sla_calculations';

    protected $fillable = [
        'unit_sla_time',
        'division_sla_time',
        'director_sla_time',
        'type',
        'status_id',
        'unit_id',
        'unit_notification',
        'division_notification',
        'director_notification',
        'sla_type_unit',
        'sla_type_division',
        'sla_type_director'
    ];

    /**
     * Relationship with Status.
     */
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    /**
     * Relationship with Group.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

}
