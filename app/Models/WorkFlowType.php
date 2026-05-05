<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkFlowType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $table = 'workflow_type';

    protected $fillable = [
        'name',
        'parent_id',
        'active',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', '1');
    }

    public function scopeChild(Builder $query): Builder
    {
        return $query->where('parent_id', '!=', null);
    }

    public function scopeNotChild(Builder $query): Builder
    {
        return $query->where('parent_id', null);
    }


    public function children()
    {
        return $this->hasMany(WorkFlowType::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(WorkFlowType::class, 'parent_id');
    }

    public function getNameColumn(): string
    {
        return 'name';
    }

    public function changeRequests()
    {
        return $this->hasMany(Change_request::class, 'workflow_type_id');
    }
}
