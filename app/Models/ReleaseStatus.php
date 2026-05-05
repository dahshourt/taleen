<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseStatus extends Model
{
    use HasFactory;

    protected $table = 'release_statuses';

    protected $fillable = [
        'name',
        'display_order',
        'color',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function releases()
    {
        return $this->hasMany(Release::class, 'release_status_id');
    }

    public function mappings()
    {
        return $this->hasMany(ReleaseStatusMapping::class);
    }

    /**
     * Get all release statuses ordered by display_order
     */
    public static function getOrdered()
    {
        return self::where('active', true)
            ->orderBy('display_order')
            ->get();
    }
}
