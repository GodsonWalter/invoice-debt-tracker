<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAccountAudit extends Model
{
    protected $fillable = [
        'target_user_id',
        'target_name',
        'target_email',
        'actor_user_id',
        'actor_global_role',
        'actor_type',
        'event',
        'reason',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
