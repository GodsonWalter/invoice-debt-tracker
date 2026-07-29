<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceLifecycleAudit extends Model
{
    protected $fillable = [
        'workspace_id',
        'workspace_name',
        'workspace_owner_id',
        'actor_user_id',
        'actor_global_role',
        'actor_type',
        'event',
        'reason',
        'metadata',
        'ip_address',
        'user_agent',
        'deleted_at',
        'restore_deadline',
        'permanent_deletion_deadline',
    ];

    protected $casts = [
        'metadata' => 'array',
        'deleted_at' => 'datetime',
        'restore_deadline' => 'datetime',
        'permanent_deletion_deadline' => 'datetime',
    ];
}
