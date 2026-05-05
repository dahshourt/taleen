<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DivisionManagers extends Model
{
    use HasFactory; // division_manager_name

    protected $table = 'division_managers';

    protected $fillable = [
        'name',
        'division_manager_email',

    ];
}
