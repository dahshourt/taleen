<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectorApprovalCr extends Model
{
    use HasFactory;

    const INACTIVE = '0';

    const APPROVED = '1';

    const REJECTED = '2';

    public static $statuses = [
        self::INACTIVE => 'Inactive',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'cr_id', 'status',
    ];

    public function isApproved()
    {
        return $this->status == self::APPROVED;
    }

    public function isRejected()
    {
        return $this->status == self::REJECTED;
    }

    public function isInactive()
    {
        return $this->status == self::INACTIVE;
    }

    public function change_request()
    {
        return $this->belongsTo(Change_request::class, 'cr_id');
    }

    public function directorApprovalCrUsers()
    {
        return $this->hasMany(DirectorApprovalCrUser::class, 'director_approval_cr_id', 'id');
    }

    public function activeDirectorApprovalCrUsers()
    {
        return $this->hasMany(DirectorApprovalCrUser::class, 'director_approval_cr_id', 'id')
            ->where('status', '0');
    }
}
