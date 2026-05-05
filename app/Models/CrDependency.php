<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CrDependency Model
 * 
 * Represents a dependency relationship between two Change Requests.
 * When CR A depends on CR B, CR A cannot progress to Design Estimation
 * until CR B reaches Delivered OR Reject status.
 *
 * @property int $id
 * @property int $cr_id The CR that has the dependency
 * @property int $depends_on_cr_id The CR that must be delivered first
 * @property string $status '0' = active, '1' = resolved
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class CrDependency extends Model
{
    protected $table = 'cr_dependencies';

    protected $fillable = [
        'cr_id',
        'depends_on_cr_id',
        'status',
    ];

    protected $casts = [
        'cr_id' => 'integer',
        'depends_on_cr_id' => 'integer',
    ];

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = '0';
    public const STATUS_RESOLVED = '1';

    /**
     * Get the CR that has this dependency (the dependent CR)
     */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(Change_request::class, 'cr_id');
    }

    /**
     * Get the CR that this dependency points to (the blocker CR)
     */
    public function dependsOnCr(): BelongsTo
    {
        return $this->belongsTo(Change_request::class, 'depends_on_cr_id');
    }

    /**
     * Scope to get only active (unresolved) dependencies
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get only resolved dependencies
     */
    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    /**
     * Check if this dependency is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if this dependency is resolved
     */
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Mark this dependency as resolved
     */
    public function markAsResolved(): bool
    {
        return $this->update(['status' => self::STATUS_RESOLVED]);
    }

    /**
     * Get all CRs that depend on a specific CR
     */
    public static function getDependentCrIds(int $dependsOnCrId): array
    {
        return self::where('depends_on_cr_id', $dependsOnCrId)
            ->active()
            ->pluck('cr_id')
            ->toArray();
    }

    /**
     * Get all CRs that a specific CR depends on (active only)
     */
    public static function getBlockingCrIds(int $crId): array
    {
        return self::where('cr_id', $crId)
            ->active()
            ->pluck('depends_on_cr_id')
            ->toArray();
    }
}
