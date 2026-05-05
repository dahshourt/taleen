<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseRisk extends Model
{
    use HasFactory;

    protected $table = 'release_risks';

    protected $fillable = [
        'risk_number',
        'release_id',
        'cr_id',
        'risk_description',
        'risk_category_id',
        'impact_level',
        'probability',
        'risk_score',
        'owner',
        'risk_status_id',
        'mitigation_plan',
        'contingency_plan',
        'date_identified',
        'target_resolution_date',
        'comment',
        'created_by',
    ];

    protected $casts = [
        'impact_level' => 'integer',
        'probability' => 'integer',
        'risk_score' => 'integer',
        'date_identified' => 'date',
        'target_resolution_date' => 'date',
    ];

    /**
     * Get formatted Risk ID (RSK-001)
     */
    public function getRiskIdAttribute(): string
    {
        return 'RSK-' . str_pad($this->risk_number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * may be changed later 
     * Get risk score level for color coding
     * Low: 1-5, Medium: 6-15, High: 16-25
     */
    public function getRiskLevelAttribute(): string
    {
        if ($this->risk_score <= 5) {
            return 'low';
        } elseif ($this->risk_score <= 15) {
            return 'medium';
        }
        return 'high';
    }

    /**
     * Get risk score color class
     */
    public function getRiskScoreColorAttribute(): string
    {
        return match ($this->risk_level) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Boot method to auto-generate risk_number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($risk) {
            // Calculate risk score
            $risk->risk_score = $risk->impact_level * $risk->probability;

            // Auto-increment risk_number globally
            $maxNumber = static::max('risk_number') ?? 0;
            $risk->risk_number = $maxNumber + 1;
        });

        static::updating(function ($risk) {
            // Recalculate risk score on update
            $risk->risk_score = $risk->impact_level * $risk->probability;
        });
    }

    // Relationships
    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function changeRequest()
    {
        return $this->belongsTo(Change_request::class, 'cr_id');
    }

    public function category()
    {
        return $this->belongsTo(RiskCategory::class, 'risk_category_id');
    }

    public function status()
    {
        return $this->belongsTo(RiskStatus::class, 'risk_status_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
