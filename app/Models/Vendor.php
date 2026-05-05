<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use DynamicColumns;

    protected $fillable = [
        'name',
        'active',
    ];
}
