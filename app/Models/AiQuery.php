<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiQuery extends Model
{
    protected $table = 'ai_queries';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'query',
        'parsed_response',
    ];

    protected $casts = [
        'parsed_response' => 'json',
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
