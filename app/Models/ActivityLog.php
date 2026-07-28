<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [

        'workspace_id',
        'user_id',
        'type',
        'description',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];
}
