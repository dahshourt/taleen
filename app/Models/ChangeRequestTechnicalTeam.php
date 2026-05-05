<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChangeRequestTechnicalTeam extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $table = 'change_request_technical_team';

    protected $fillable = [
        'cr_id',
        'technical_team_id', // now referencing groups
    ];

    // Define relation to ChangeRequest
    public function changeRequest()
    {
        return $this->belongsTo(ChangeRequest::class, 'cr_id');
    }

    // Define relation to Group (previously TechnicalTeam)
    public function group()
    {
        return $this->belongsTo(Group::class, 'technical_team_id');
    }
}
