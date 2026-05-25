<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    use HasFactory;

    protected $table = 'workspaces';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'subdomain',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot(['role', 'is_active', 'activation_token'])
            ->withTimestamps();
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'workspace_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'workspace_id');
    }
 
}
