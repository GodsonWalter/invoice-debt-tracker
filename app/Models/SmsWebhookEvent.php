<?php

namespace App\Models;

use Database\Factories\SmsWebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsWebhookEvent extends Model
{
    /** @use HasFactory<SmsWebhookEventFactory> */
    use HasFactory;

    protected $fillable = [
        'dedupe_key',
        'provider',
        'provider_account_id',
        'provider_message_id',
        'event_type',
        'payload',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
