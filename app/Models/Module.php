<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    public function module_rules()
    {
        return $this->hasMany(Module_Rules::class, 'module_id');

    }
}
