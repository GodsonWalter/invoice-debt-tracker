<?php

namespace App\Models;

use Database\Factories\SmsConnectionAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsConnectionAudit extends Model
{
    /** @use HasFactory<SmsConnectionAuditFactory> */
    use HasFactory;

    protected $fillable = [
        'sms_connection_id',
        'workspace_id',
        'actor_user_id',
        'event',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SmsConnection::class, 'sms_connection_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
