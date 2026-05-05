<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Defect extends Model
{
    use HasFactory;

    public $table = 'defects';

    protected $fillable = [
        'cr_id',
        'subject',
        'group_id',
        'status_id',
        'created_by',
    ];

    public function change_request()
    {
        return $this->belongsTo(Change_request::class, 'cr_id');

    }

    public function assigned_team()
    {
        return $this->belongsTo(Group::class, 'group_id');

    }

    public function current_status()
    {
        return $this->belongsTo(Status::class, 'status_id');

    }

    public function User_created()
    {
        return $this->belongsTo(User::class, 'created_by');

    }

    public function attachments()
    {
        return $this->hasMany(DefectAttachment::class, 'defect_id', 'id');
    }

    public function logs()
    {
        return $this->hasMany(DefectLog::class, 'defect_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(DefectComment::class, 'defect_id', 'id');
    }
}
