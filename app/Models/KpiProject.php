<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiProject extends Model
{
    use HasFactory;

    protected $table = 'kpi_projects';

    protected $fillable = [
        'kpi_id',
        'project_id',
    ];

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}


