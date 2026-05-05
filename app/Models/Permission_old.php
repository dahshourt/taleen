<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission_old extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_rule_id',
        'group_id',
        'active',

    ];

    public function permision_module_rule()
    {

        return $this->belongsTo(Module_Rules::class, 'module_rule_id');
    }

    public function group()
    {

        return $this->belongsTo(Group::class, 'group_id');
    }
}
