<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Director extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_name',
        'email',
        'status',
        'cr_director_approval',
    ];

    public function scopeCrDirectorApproval($query)
    {
        return $query->where('cr_director_approval', 1);
    }
}
