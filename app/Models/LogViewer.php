<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LogViewer extends Model
{
    protected $table = 'log_viewers';

    protected $fillable = [
        'level',
        'level_name',
        'message',
        'user_agent',
        'ip_address',
        'http_method',
        'url',
        'referer_url',
        'headers',
        'context',
        'extra',
        'trace_stack',
        'log_hash',
        'solved',
        'solved_by',
        'solved_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'context' => 'array',
        'extra' => 'array',
        'trace_stack' => 'array',
        'solved' => 'boolean',
        'solved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who solved the log entry
     */
    public function solver()
    {
        return $this->belongsTo(User::class, 'solved_by');
    }

    /**
     * Scope to apply all filters from request
     */
    public function scopeFilters($query)
    {
        return $query
            ->when(request()->query('level'), fn (Builder $q, $level) => $q->byLevel($level))
            ->when(request()->query('status'), fn (Builder $q, $status) => $q->byStatus($status))
            ->when(request()->query('date'), fn (Builder $q, $date) => $q->whereDate('created_at', $date))
            ->when(request()->query('search'), fn (Builder $q, $search) => $q->search($search));
    }

    /**
     * Scope to filter by level name
     */
    public function scopeByLevel($query, $level)
    {
        if ($level) {
            return $query->where('level_name', $level);
        }

        return $query;
    }

    /**
     * Scope to filter by solved status
     */
    public function scopeByStatus($query, $status)
    {
        if ($status === 'solved') {
            return $query->where('solved', true);
        }

        if ($status === 'unresolved') {
            return $query->where('solved', false)->where('level_name', 'ERROR');
        }

        return $query;
    }

    /**
     * Scope to search in message
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Check if log should be considered as needing resolution
     */
    public function needsResolution(): bool
    {
        return $this->level_name === 'ERROR' && ! $this->solved;
    }
}
