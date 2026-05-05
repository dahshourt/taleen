<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrerequisiteLog extends Model
{
    use HasFactory;

    protected $table = 'prerequisites_logs';

    protected $fillable = [
        'prerequisite_id',
        'user_id',
        'log_text',
    ];

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Prerequisite::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
