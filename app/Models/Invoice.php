<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'workspace_id',
        'client_id',
        'currency_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function businessProfile(): ?BusinessProfile
    {
        return $this->workspace?->businessProfile;
    }

    public function getPaidAmountAttribute(): float
    {
        return $this->total_paid;
    }

    public function getTotalPaidAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }

        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(round((float) $this->total_amount - $this->total_paid, 2), 0.0);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->is_fully_paid;
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->remaining_balance <= 0 && (float) $this->total_amount > 0;
    }

    public function getIsPartiallyPaidAttribute(): bool
    {
        return $this->total_paid > 0 && ! $this->is_fully_paid;
    }

    public function getCurrencySymbolAttribute(): string
    {
        return $this->currency?->symbol ?? $this->workspace?->currency_symbol ?? '';
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        $currency = $this->currency ?? $this->workspace?->currency;

        return $currency
            ? $currency->formatMoney($amount)
            : number_format((float) $amount, 2);
    }
}
