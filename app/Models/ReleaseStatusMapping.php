<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseStatusMapping extends Model
{
    use HasFactory;

    protected $table = 'release_status_mappings';

    protected $fillable = [
        'release_status_id',
        'cr_status_name',
    ];

    public function releaseStatus()
    {
        return $this->belongsTo(ReleaseStatus::class);
    }

    /**
     * Get the release status for a given CR status name
     */
    public static function getReleaseStatusFor(string $crStatusName): ?ReleaseStatus
    {
        $mapping = self::where('cr_status_name', $crStatusName)->first();
        return $mapping ? $mapping->releaseStatus : null;
    }
}
