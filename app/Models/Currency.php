<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'symbol',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'default_currency_id');
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'currency_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'currency_id');
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return $this->symbol.number_format((float) $amount, 2);
    }
}
