<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CabCr extends Model
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
        return $this->belongsTo(Change_request::class, 'cr_id'); // how is it work "belongs_to" ? the right is "belongsTo"
    }

    public function cab_cr_user()
    {
        return $this->hasMany(CabCrUser::class, 'cab_cr_id', 'id');
    }

    public function cabCrUsers()
    {
        return $this->hasMany(CabCrUser::class, 'cab_cr_id', 'id');
    }

    /**
     * Get only active (status = 0) cab_cr_users
     */
    public function activeCabCrUsers()
    {
        return $this->hasMany(CabCrUser::class, 'cab_cr_id', 'id')
            ->where('status', '0');
    }
}
