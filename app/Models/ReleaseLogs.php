<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseLogs extends Model
{
    use HasFactory;

    public $table = 'release_logs';

    protected $fillable = [
        'release_id',
        'user_id',
        'log_text',
        'status_id',
    ];

    public function release()
    {
        return $this->belongsTo(Release::class, 'release_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}
