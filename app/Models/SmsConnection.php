<?php

namespace App\Models;

use Database\Factories\SmsConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsConnection extends Model
{
    /** @use HasFactory<SmsConnectionFactory> */
    use HasFactory;

    public const TYPE_SHARED = 'shared';

    public const TYPE_WORKSPACE = 'workspace';

    public const TYPES = [self::TYPE_SHARED, self::TYPE_WORKSPACE];

    public const STATUS_NOT_CONNECTED = 'not_connected';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_RESTRICTED = 'restricted';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'workspace_id',
        'connection_type',
        'provider',
        'provider_account_id',
        'sender',
        'messaging_service_id',
        'status',
        'is_enabled',
        'last_synced_at',
        'last_error',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'auth_token' => 'encrypted',
        'is_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(SmsTemplate::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(SmsMessageLog::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SmsConnectionAudit::class);
    }

    public function isReady(): bool
    {
        return $this->is_enabled
            && $this->status === self::STATUS_CONNECTED
            && filled($this->provider_account_id)
            && filled($this->auth_token)
            && (filled($this->sender) || filled($this->messaging_service_id));
    }
}
