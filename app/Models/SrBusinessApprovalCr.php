<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SrBusinessApprovalCr extends Model
{
    use HasFactory;

    protected $table = 'sr_business_approval_crs';

    protected $fillable = [
        'cr_id',
        'status',
    ];

    public function cr()
    {
        return $this->belongsTo(Change_request::class, 'cr_id');
    }

    public function approvalUsers()
    {
        return $this->hasMany(SrBusinessApprovalCrUser::class, 'sr_business_approval_cr_id');
    }

    // Status Constants
    public const PENDING = '0';
    public const APPROVED = '1';
    public const REJECTED = '2';
}
