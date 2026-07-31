<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    public const PLATFORM_ROLE_OWNER = 'owner';

    public const PLATFORM_ROLES = ['owner', 'admin', 'manager', 'staff', 'user'];

    public const PLATFORM_MANAGEMENT_ROLES = ['owner', 'admin', 'manager', 'staff'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    // fillable and hidden attributes
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'address',
        'is_active',
        'default_currency_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot(['role', 'is_active', 'activation_token'])
            ->withTimestamps();
    }

    public function ownedDeletedWorkspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_id')->onlyTrashed();
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function isPlatformOwner(): bool
    {
        return $this->role === self::PLATFORM_ROLE_OWNER;
    }

    public function canManagePlatformUsers(): bool
    {
        return in_array($this->role, self::PLATFORM_MANAGEMENT_ROLES, true);
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }
}
