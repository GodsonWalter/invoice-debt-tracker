<?php

namespace App\Models;

use Database\Factories\SmsMessageLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessageLog extends Model
{
    /** @use HasFactory<SmsMessageLogFactory> */
    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'workspace_id',
        'sms_connection_id',
        'invoice_id',
        'reminder_schedule_id',
        'recipient_phone',
        'sender',
        'message_type',
        'status',
        'idempotency_key',
        'provider_message_id',
        'rendered_body',
        'request_payload',
        'response_payload',
        'error_message',
        'attempts',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SmsConnection::class, 'sms_connection_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reminderSchedule(): BelongsTo
    {
        return $this->belongsTo(ReminderSchedule::class);
    }
}
