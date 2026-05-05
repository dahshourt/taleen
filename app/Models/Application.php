<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Application extends Model
{
    use DynamicColumns;

    protected $fillable = [
        'id',
        'name',
        'app_support',
        'active',
        'is_sr_system',
        'workflow_type_id',
        'file',
        'parent_id',
        'operation_dm',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', '1');
    }

    public function workflow_type()
    {
        return $this->belongsTo(WorkFlowType::class);
    }

    public function group_applications()
    {
        return $this->hasMany(GroupApplications::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function operationDm(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'operation_dm');
    }

    /** Direct children of this record */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Top-level only (no parent) */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /** Safety: prevent self-parenting */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->id && $model->parent_id === $model->id) {
                throw new InvalidArgumentException('A record cannot be its own parent.');
            }
        });
    }
}
