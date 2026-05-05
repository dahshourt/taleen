<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Technical_team extends Model
{
    use HasFactory;

    public $table = 'technical_teams';

    public function changeRequests()
    {
        return $this->belongsToMany(ChangeRequest::class, 'change_request_technical_team', 'technical_team_id', 'cr_id');
    }
}
