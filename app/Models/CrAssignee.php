<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrAssignee extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function changeRequest()
    {
        return $this->belongsTo(ChangeRequest::class, 'cr_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
