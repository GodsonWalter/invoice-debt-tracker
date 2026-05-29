<?php

namespace App\Models;

use Database\Factories\BusinessProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BusinessProfile extends Model
{
    /** @use HasFactory<BusinessProfileFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'workspace_id',
        'business_name',
        'logo',
        'email',
        'phone',
        'address',
        'tax_id',
        'city',
        'state',
        'postal_code',
        'country',
        'business_description',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->business_name ?: $this->workspace?->name ?: 'Business';
    }

    public function getLogoUrlAttribute(): string
    {
        if ($this->logo && Storage::disk('public')->exists($this->logo)) {
            return Storage::disk('public')->url($this->logo);
        }

        return asset('images/business-logo-placeholder.svg');
    }

    public function getFormattedAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    public function getContactLinesAttribute(): array
    {
        return array_values(array_filter([
            $this->formatted_address,
            $this->phone,
            $this->email,
        ]));
    }

    public function getTaxLabelAttribute(): ?string
    {
        return $this->tax_id ? 'Tax ID: '.$this->tax_id : null;
    }
}
