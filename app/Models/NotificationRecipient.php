<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_rule_id', 'channel',
        'recipient_type', 'recipient_identifier',
    ];

    public function rule()
    {
        return $this->belongsTo(NotificationRule::class, 'notification_rule_id');
    }
}
