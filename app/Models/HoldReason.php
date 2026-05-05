<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HoldReason extends Model
{
    protected $fillable = [
        'name',
        'status',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '1');
    }
}
