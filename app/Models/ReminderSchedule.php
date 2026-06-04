<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderSchedule extends Model
{
    public const DIRECTION_BEFORE_DUE = 'before_due';

    public const DIRECTION_AFTER_DUE = 'after_due';

    public const DIRECTIONS = [
        self::DIRECTION_BEFORE_DUE,
        self::DIRECTION_AFTER_DUE,
    ];

    protected $fillable = [
        'workspace_id',
        'name',
        'days_offset',
        'direction',
        'is_active',
    ];

    protected $casts = [
        'days_offset' => 'integer',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class, 'reminder_schedule_id');
    }
}
