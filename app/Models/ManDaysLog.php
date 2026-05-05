<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManDaysLog extends Model
{
    use HasFactory;

    protected $table = 'man_days_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'group_id',
        'user_id',
        'cr_id',
        'man_day',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Relationship to Group
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship to ChangeRequest
     */
    public function changeRequest()
    {
        return $this->belongsTo(ChangeRequest::class, 'cr_id');
    }
}
