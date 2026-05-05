<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prerequisite extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subject',
        'promo_id',
        'group_id',
        'created_by',
        'requester_department',
        'requester_mobile',
        'status_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requester_mobile' => 'string',
    ];

    /**
     * Get the promo that owns the prerequisite.
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(Change_request::class);
    }

    /**
     * Get the group that owns the prerequisite.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user who created the prerequisite.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the status of the prerequisite.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get all attachments for the prerequisite.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PrerequisiteAttachment::class);
    }

    /**
     * Get all comments for the prerequisite.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PrerequisiteComment::class)->latest();
    }

    /**
     * Get the latest comment for the prerequisite.
     */
    public function latestComment(): HasOne
    {
        return $this->hasOne(PrerequisiteComment::class)->latestOfMany();
    }

    /**
     * Get the logs for the prerequisite.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PrerequisiteLog::class)->latest();
    }

    /**
     * Scope a query to only include prerequisites for a specific status.
     */
    public function scopeWithStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope a query to only include prerequisites for a specific group.
     */
    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Check if the prerequisite is open.
     */
    public function isOpen(): bool
    {
        return $this->status?->status_name === 'Open';
    }

    /**
     * Check if the prerequisite is closed.
     */
    public function isClosed(): bool
    {
        return $this->status?->status_name === 'Closed';
    }
}
