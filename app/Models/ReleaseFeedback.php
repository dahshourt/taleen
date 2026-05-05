<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseFeedback extends Model
{
    use HasFactory;

    protected $table = 'release_feedbacks';

    protected $fillable = [
        'release_id',
        'feedback',
        'created_by',
    ];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
