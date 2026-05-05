<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
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

    protected $table = 'workflow';

    protected $fillable = [
        'from_status_id', 'from_status_name', 'to_status_id', 'to_status_name', 'default_to_status', 'active', 'to_status_label', 'active',

    ];

    public function from_status()
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    public function to_status()
    {
        return $this->belongsTo(Status::class, 'to_status_id');
    }
}
