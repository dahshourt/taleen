<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'description', 'event_class',
        'conditions', 'template_id', 'is_active', 'priority',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_rule_id');
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class, 'notification_rule_id');
    }
}
