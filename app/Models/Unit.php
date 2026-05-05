<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use DynamicColumns;

    protected $fillable = [
        'name',
        'manager_name',
        'status',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '1');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'id');
    }

    /**
     * Relationship with SLA Calculations.
     * (Each unit may have many SLA rules)
     */
    public function slaCalculations()
    {
        return $this->hasMany(SlaCalculation::class, 'unit_id');
    }
}
