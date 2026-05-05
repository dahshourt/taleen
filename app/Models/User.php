<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use DynamicColumns, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The code of status active type.
     *
     * @var int
     */
    const ACTIVE = 1;

    /**
     * The code of status inactive type.
     *
     * @var int
     */
    const INACTIVE = 0;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'user_name',
        'email',
        'password',
        'default_group',
        'user_type',
        'active',
        // 'role_id', old roles system
        'unit_id',
        'man_power',
        'department_id',
        'failed_attempts',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = bcrypt($password);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', '1');
    }

    public function scopeSystemAdmin(Builder $query): Builder
    {
        return $query->where('email', 'admin@te.eg');
    }

    public function user_groups()
    {
        return $this->hasMany(UserGroups::class, 'user_id', 'id')->whereHas('group', function ($query) {
            $query->where('active', '1');
        });
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'user_groups', 'user_id', 'group_id')
            ->where('groups.active', '1');
    }

    public function user_report_to()
    {
        return $this->hasOne(Pivotusersrole::class);
    }

    public function defualt_group()
    {

        return $this->belongsTo(Group::class, 'default_group', 'id');
    }

    public function role()
    {

        return $this->belongsTo(Role::class);
    }

    public function unit()
    {

        return $this->belongsTo(Unit::class)->select('id', 'name');
    }

    public function department()
    {

        return $this->belongsTo(Department::class)->select('id', 'name');
    }

    public function logs()
    {
        return $this->hasMany(Log::class);
    }

    public function isSystemAdmin(): bool
    {
        return $this->email === 'admin@te.eg';
    }
}
