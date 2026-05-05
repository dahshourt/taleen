<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Model;

class CrType extends Model
{
    use DynamicColumns;

    protected $table = 'cr_types';

    protected $fillable = ['name'];
}
