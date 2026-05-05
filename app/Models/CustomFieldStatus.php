<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldStatus extends Model
{
    use HasFactory;

    protected $table = 'custom_field_statuses';

    protected $fillable = [
        'custom_field_id',
        'status_id',
        'log_message',
        'user_id',
    ];

    /**
     * Get the custom field that owns this status log message.
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * Get the status associated with this log message.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the user who created/updated this log message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
