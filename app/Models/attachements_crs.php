<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachements_crs extends Model
{
    use HasFactory;

    public $table = 'attachements_crs';

    protected $fillable = [
        'cr_id',
        'user_id',
        'file',
        'attachment',
        'visible',
        'description',
        'file_name',
        'size',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function change_reqest()
    {
        return $this->belongsTo(Change_request::class, 'cr_id');
    }
}
