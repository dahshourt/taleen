<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Model;

class DeploymentImpact extends Model
{
    use DynamicColumns;

    protected $table = 'deployment_impacts';

    protected $fillable = [
        'name',
        'active',
    ];
}
