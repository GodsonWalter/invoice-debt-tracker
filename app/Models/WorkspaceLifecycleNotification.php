<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceLifecycleNotification extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id',
        'workspace_name',
        'workspace_owner_id',
        'recipient_email',
        'notification_type',
        'dedupe_key',
        'status',
        'scheduled_for',
        'sent_at',
        'failed_at',
        'attempts',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
