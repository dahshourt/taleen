<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_rule_id', 'template_id', 'event_class', 'event_data',
        'subject', 'body', 'recipients_to', 'recipients_cc', 'recipients_bcc',
        'status', 'error_message', 'sent_at', 'retry_count',
        'related_model_type', 'related_model_id',
    ];

    protected $casts = [
        'event_data' => 'array',
        'recipients_to' => 'array',
        'recipients_cc' => 'array',
        'recipients_bcc' => 'array',
        'sent_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }

    public function template()
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
