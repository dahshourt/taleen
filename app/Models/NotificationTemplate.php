<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'description', 'subject', 'body',
        'available_placeholders', 'is_active',
    ];

    protected $casts = [
        'available_placeholders' => 'array',
        'is_active' => 'boolean',
    ];

    public function rules()
    {
        return $this->hasMany(NotificationRule::class, 'template_id');
    }
}
