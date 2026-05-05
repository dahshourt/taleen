<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseCrAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_id',
        'cr_id',
        'description',
        'type',
        'file_name',
        'file_path',
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
