<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReleaseAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_id',
        'file_name',
        'file_path',
        'created_by',
    ];

    public function release()
    {
        return $this->belongsTo(Release::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
