<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

class CurrencyService
{
    public function activeCurrencies(): Collection
    {
        return Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function activeCurrencyRule(): object
    {
        return Rule::exists('currencies', 'id')->where('is_active', true);
    }

    public function defaultCurrency(): ?Currency
    {
        return Currency::query()
            ->where('code', 'USD')
            ->where('is_active', true)
            ->first()
            ?? Currency::query()->where('is_active', true)->orderBy('code')->first();
    }

    public function defaultCurrencyForUser(?User $user): ?Currency
    {
        if ($user?->defaultCurrency?->is_active) {
            return $user->defaultCurrency;
        }

        return $this->defaultCurrency();
    }

    public function defaultCurrencyForWorkspace(Workspace $workspace): ?Currency
    {
        if ($workspace->currency?->is_active) {
            return $workspace->currency;
        }

        return $this->defaultCurrencyForUser(auth()->user());
    }

    public function formatMoney(float|int|string|null $amount, ?Currency $currency = null): string
    {
        $currency ??= $this->defaultCurrency();

        if (! $currency) {
            return number_format((float) $amount, 2);
        }

        return $currency->formatMoney($amount);
    }
}
