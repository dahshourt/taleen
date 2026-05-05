<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrerequisiteComment extends Model
{
    use HasFactory;

    protected $table = 'prerequisites_comments';

    protected $fillable = [
        'prerequisite_id',
        'user_id',
        'comment',
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
