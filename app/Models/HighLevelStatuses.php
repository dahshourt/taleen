<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HighLevelStatuses extends Model
{
    use HasFactory;

    protected $table = 'high_level_statuses';

    protected $fillable = [
        'name',
        'active',

    ];

    public function status()
    {
        return $this->hasMany(Status::class, 'high_level_status_id');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class, 'high_level_status_id');
    }
}
