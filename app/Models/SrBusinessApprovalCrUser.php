<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SrBusinessApprovalCrUser extends Model
{
    use HasFactory;

    protected $table = 'sr_business_approval_cr_users';

    protected $fillable = [
        'user_id',
        'sr_business_approval_cr_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function srBusinessApprovalCr()
    {
        return $this->belongsTo(SrBusinessApprovalCr::class, 'sr_business_approval_cr_id');
    }

    // Status Constants
    public const PENDING = '0';
    public const APPROVED = '1';
    public const REJECTED = '2';
}
