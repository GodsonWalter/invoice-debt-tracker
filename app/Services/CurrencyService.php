<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

class CurrencyService
{
    /**
     * @param  array{search?: ?string, status?: string, sort?: string, direction?: string}  $filters
     */
    public function paginatedCurrencies(array $filters = []): LengthAwarePaginator
    {
        $sortableColumns = ['code', 'symbol', 'name', 'is_active', 'created_at'];
        $sort = in_array($filters['sort'] ?? null, $sortableColumns, true)
            ? $filters['sort']
            : 'code';
        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
        $status = $filters['status'] ?? 'all';
        $search = $filters['search'] ?? null;

        return Currency::query()
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('symbol', 'like', '%'.$search.'%');
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(10)
            ->withQueryString();
    }

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

    public function createCurrency(array $validated): Currency
    {
        return Currency::create([
            'code' => strtoupper($validated['code']),
            'symbol' => $validated['symbol'],
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);
    }

    public function updateCurrency(Currency $currency, array $validated): Currency
    {
        $currency->update([
            'symbol' => $validated['symbol'],
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return $currency;
    }

    public function toggleCurrency(Currency $currency): Currency
    {
        $currency->update([
            'is_active' => ! $currency->is_active,
        ]);

        return $currency;
    }

    public function deactivateCurrency(Currency $currency): Currency
    {
        $currency->update([
            'is_active' => false,
        ]);

        return $currency;
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
