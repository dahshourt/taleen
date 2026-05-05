<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefectAttachment extends Model
{
    use HasFactory;

    public $table = 'defect_attachments';

    protected $fillable = [
        'defect_id',
        'user_id',
        'file',
    ];

    public function defect()
    {
        return $this->belongsTo(Defect::class, 'defect_id');

    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');

    }
}
