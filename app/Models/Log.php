<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'cr_id',
        'user_id',
        'log_text',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ChangeRequest()
    {
        return $this->belongsTo(Change_request::class, 'cr_id');
    }
}
