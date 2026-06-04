<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'invoice_prefix',
        'currency_id',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class, 'workspace_id');
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'workspace_id');
    }

    public function invoiceEmailLogs(): HasMany
    {
        return $this->hasMany(InvoiceEmailLog::class, 'workspace_id');
    }

    public function reminderSchedules(): HasMany
    {
        return $this->hasMany(ReminderSchedule::class, 'workspace_id');
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class, 'workspace_id');
    }

    public function getCurrencySymbolAttribute(): string
    {
        return $this->currency?->symbol ?? '';
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return $this->currency
            ? $this->currency->formatMoney($amount)
            : number_format((float) $amount, 2);
    }
}
