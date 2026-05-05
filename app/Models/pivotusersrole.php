<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class pivotusersrole extends Model
{
    use HasFactory;

    protected $table = 'pivotusersroles';

    protected $fillable = ['report_to', 'user_id'];

    protected $hidden = ['report_to', 'user_id', 'created_at', 'updated_at'];

    public function reporter()
    {

        return $this->belongsTo(User::class, 'report_to');
    }

    public function user()
    {

        return $this->belongsTo(User::class, 'user_id');
    }
}
