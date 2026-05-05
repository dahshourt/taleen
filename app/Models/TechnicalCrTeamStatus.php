<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalCrTeamStatus extends Model
{
    use HasFactory;

    protected $table = 'technical_cr_team_statuses';

    protected $fillable = [
        'technical_cr_team_id',
        'old_status_id',
        'new_status_id',
        'user_id',
        'note',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* ===================== Relationships ===================== */

    public function technicalCrTeam(): BelongsTo
    {
        return $this->belongsTo(TechnicalCrTeam::class);
    }

    public function oldStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'old_status_id');
    }

    public function newStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ======================== Scopes ========================= */

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('technical_cr_team_id', $teamId);
    }

    public function scopeWhereNewIn($query, array $statusIds)
    {
        return $query->whereIn('new_status_id', $statusIds);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /* ===== Optional safety net if DB triggers aren’t used ===== */

    protected static function booted(): void
    {
        // Auto-fill old_status_id from the team snapshot if omitted
        static::creating(function (self $model) {
            if (is_null($model->old_status_id) && $model->technicalCrTeam) {
                $model->old_status_id = $model->technicalCrTeam->current_status_id;
            }
        });

        // Keep team snapshot current (skip if you already use DB triggers)
        static::created(function (self $model) {
            if ($model->relationLoaded('technicalCrTeam') || $model->technicalCrTeam()->exists()) {
                $model->technicalCrTeam()->update(['current_status_id' => $model->new_status_id]);
            }
        });
    }
}
