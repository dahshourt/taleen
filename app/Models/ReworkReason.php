<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReworkReason extends Model
{
    use HasFactory;

    protected $table = 'rework_reasons';
    protected $fillable = ['name', 'active'];
}
