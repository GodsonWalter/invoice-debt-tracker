<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyIndexRequest;
use App\Http\Requests\StoreCurrencyRequest;
use App\Http\Requests\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    private function authorizeCurrencyManager(): void
    {
        if (! in_array(Auth::user()?->role, ['owner', 'admin'], true)) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage currencies.')
            );
        }
    }

    public function index(CurrencyIndexRequest $request, CurrencyService $currencyService): View
    {
        $this->authorizeCurrencyManager();

        $filters = $request->validated();

        return view('currencies.index', [
            'currencies' => $currencyService->paginatedCurrencies($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorizeCurrencyManager();

        return view('currencies.create');
    }

    public function store(StoreCurrencyRequest $request, CurrencyService $currencyService): RedirectResponse
    {
        $this->authorizeCurrencyManager();

        $currency = $currencyService->createCurrency($request->validated());

        return redirect()
            ->route('currencies.index', $currency)
            ->with('success', 'Currency created successfully.');
    }

    public function edit(Currency $currency): View
    {
        $this->authorizeCurrencyManager();

        return view('currencies.edit', [
            'currency' => $currency,
        ]);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency, CurrencyService $currencyService): RedirectResponse
    {
        $this->authorizeCurrencyManager();

        $currencyService->updateCurrency($currency, $request->validated());

        return redirect()
            ->route('currencies.index')
            ->with('success', 'Currency updated successfully.');
    }

    public function toggle(Currency $currency, CurrencyService $currencyService): RedirectResponse
    {
        $this->authorizeCurrencyManager();

        $currencyService->toggleCurrency($currency);

        return back()->with('success', 'Currency status updated successfully.');
    }

    public function destroy(Currency $currency, CurrencyService $currencyService): RedirectResponse
    {
        $this->authorizeCurrencyManager();

        $currencyService->deleteCurrency($currency);

        return back()->with('success', 'Currency deleted successfully.');
    }

    public function restore(string $currency, CurrencyService $currencyService): RedirectResponse
    {
        $this->authorizeCurrencyManager();

        $deletedCurrency = Currency::onlyTrashed()->findOrFail($currency);
        $currencyService->restoreCurrency($deletedCurrency);

        return redirect()
            ->route('currencies.index', ['status' => 'deleted'])
            ->with('success', 'Currency restored successfully.');
    }
}
