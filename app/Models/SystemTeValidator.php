<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Model;

class SystemTeValidator extends Model
{
    use DynamicColumns;

    const ACTIVE = '1';
    const INACTIVE = '0';

    public static $actives = [
        self::ACTIVE => 'Active',
        self::INACTIVE => 'Inactive',
    ];

    protected $fillable = [
        'user_id', 'system_id', 'active',
    ];

    protected $attributes = [
        'active' => '0',
    ];

    public function isActive()
    {
        return $this->active == self::ACTIVE;
    }

    public function isInactive()
    {
        return $this->active == self::INACTIVE;
    }

    public function system()
    {
        return $this->belongsTo(Application::class, 'system_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
