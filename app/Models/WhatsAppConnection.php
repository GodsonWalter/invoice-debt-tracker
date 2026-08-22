<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConnection extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_connections';

    public const TYPE_SHARED = 'shared';

    public const TYPE_TENANT = 'tenant';

    public const TYPES = [self::TYPE_SHARED, self::TYPE_TENANT];

    public const STATUS_NOT_CONNECTED = 'not_connected';

    public const STATUS_PENDING = 'pending_setup';

    public const STATUS_PENDING_VERIFICATION = 'pending_business_verification';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_RESTRICTED = 'restricted';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'workspace_id',
        'connection_type',
        'business_portfolio_id',
        'waba_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'token_expires_at',
        'status',
        'is_enabled',
        'last_synced_at',
        'last_error',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_enabled' => 'boolean',
        'metadata' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whatsapp_connection_id');
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(WhatsAppMessageLog::class, 'whatsapp_connection_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(WhatsAppConnectionAudit::class, 'whatsapp_connection_id');
    }

    public function isReady(): bool
    {
        return $this->is_enabled
            && $this->status === self::STATUS_CONNECTED
            && filled($this->phone_number_id)
            && filled($this->access_token);
    }
}
