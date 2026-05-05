<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SrRequesterApproval extends Model
{
    protected $table = 'sr_requester_approvals';

    protected $fillable = [
        'requester_id',
        'approval_level',
        'active',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approvalUsers()
    {
        return $this->belongsToMany(User::class, 'sr_requester_approval_user', 'sr_requester_approval_id', 'user_id');
    }
}
