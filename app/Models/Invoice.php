<?php

namespace App\Models;

use App\Services\MoneyCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_VOID = 'void';

    public const REMINDER_STATUS_NOT_DUE = 'not_due';

    public const REMINDER_STATUS_SCHEDULED = 'scheduled';

    public const REMINDER_STATUS_SENT = 'sent';

    public const REMINDER_STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_PARTIAL,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
        self::STATUS_VOID,
    ];

    public const REMINDER_STATUSES = [
        self::REMINDER_STATUS_NOT_DUE,
        self::REMINDER_STATUS_SCHEDULED,
        self::REMINDER_STATUS_SENT,
        self::REMINDER_STATUS_OVERDUE,
    ];

    protected $fillable = [
        'workspace_id',
        'client_id',
        'currency_id',
        'invoice_number',
        'public_token',
        'issue_date',
        'due_date',
        'status',
        'reminder_status',
        'voided_at',
        'voided_by',
        'void_reason',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'notes',
        'viewed_at',
        'downloaded_at',
        'printed_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'viewed_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'printed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if (! $invoice->public_token) {
                $invoice->public_token = (string) Str::uuid();
            }
        });
    }

    public function ensurePublicToken(): self
    {
        if (! $this->public_token) {
            $this->forceFill(['public_token' => (string) Str::uuid()])->save();
        }

        return $this;
    }

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

    public function emailLogs(): HasMany
    {
        return $this->hasMany(InvoiceEmailLog::class, 'invoice_id');
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class, 'invoice_id');
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
        $money = app(MoneyCalculator::class);

        if ($this->relationLoaded('payments')) {
            return $money->toFloat($money->normalize($this->payments->sum('amount')));
        }

        return $money->toFloat($money->normalize($this->payments()->sum('amount')));
    }

    public function getRemainingBalanceAttribute(): float
    {
        $money = app(MoneyCalculator::class);
        $remaining = $money->subtract($this->total_amount, $this->total_paid);

        return $remaining->isNegative() ? 0.0 : $money->toFloat($remaining);
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
