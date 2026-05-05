<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_id',
        'role_id',
        'user_id',
        'mobile',
        'created_by',
    ];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function role()
    {
        return $this->belongsTo(ReleaseTeamRole::class, 'role_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
