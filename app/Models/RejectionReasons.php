<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectionReasons extends Model
{
    use HasFactory;

    public $table = 'rejection_reasons';

    protected $fillable = [
        'id', 'name', 'active', 'workflow_type_id',
    ];

    public function workflowType()
    {
        return $this->belongsTo(WorkFlowType::class, 'workflow_type_id');
    }

    public function getNameColumn(): string
    {
        return 'name';
    }
}
