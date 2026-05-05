<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module_Rules extends Model
{
    use HasFactory;

    protected $table = 'module_rules';

    public function permission()
    {
        return $this->hasMany(permission::class, 'module_rule_id');

    }
}
