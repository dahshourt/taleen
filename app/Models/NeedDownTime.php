<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Model;

class NeedDownTime extends Model
{
    use DynamicColumns;

    protected $table = 'need_down_times';

    protected $fillable = [
        'name',
        'active',
    ];
}
