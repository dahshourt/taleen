<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SrTechnicalCrTeamStatus extends Model
{
    use HasFactory;

    protected $table = 'sr_technical_cr_team_statuses';

    protected $fillable = [
        'technical_cr_team_id', 'old_status_id', 'new_status_id', 'user_id', 'note',
    ];

    public function sr_technical_cr_team()
    {
        return $this->belongsTo(SrTechnicalCrTeam::class, 'technical_cr_team_id');
    }

    public function srTechnicalCrTeam()
    {
        return $this->belongsTo(SrTechnicalCrTeam::class, 'technical_cr_team_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function oldStatus()
    {
        return $this->belongsTo(Status::class, 'old_status_id');
    }

    public function newStatus()
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }
}
