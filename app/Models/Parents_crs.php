<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parents_crs extends Model
{
    use DynamicColumns;

    protected $fillable = [
        'application_name',
        'name',
        'active',
        'file',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', '1');
    }

    public function change_request()
    {
        return $this->belongsTo(Change_request::class,
            'name'); // how is it work "belongs_to" ? the right is "belongsTo"

    }
}
