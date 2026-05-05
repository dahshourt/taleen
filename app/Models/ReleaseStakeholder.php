<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseStakeholder extends Model
{
    use HasFactory;

    protected $table = 'release_stakeholders';

    protected $fillable = [
        'release_id',
        'cr_id',
        'high_impact_stakeholder',
        'moderate_impact_stakeholder',
        'low_impact_stakeholder',
        'created_by',
    ];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function changeRequest()
    {
        return $this->belongsTo(Change_request::class, 'cr_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
