<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'report_type',
        'format',
        'filters',
        'status',
        'path',
        'error_message',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
