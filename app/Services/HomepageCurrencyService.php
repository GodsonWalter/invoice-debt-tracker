<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class HomepageCurrencyService
{
    private const DEFAULT_CURRENCY_CODE = 'USD';

    /**
     * @var array<string, string>
     */
    private const CURRENCY_SYMBOLS = [
        'AED' => 'د.إ',
        'AUD' => '$',
        'BRL' => 'R$',
        'CAD' => '$',
        'CHF' => 'CHF',
        'CNY' => '¥',
        'DKK' => 'kr',
        'EGP' => 'E£',
        'EUR' => '€',
        'GBP' => '£',
        'GHS' => '₵',
        'HKD' => '$',
        'INR' => '₹',
        'JPY' => '¥',
        'KES' => 'KSh',
        'KRW' => '₩',
        'MAD' => 'د.م.',
        'MXN' => '$',
        'NGN' => '₦',
        'NOK' => 'kr',
        'NZD' => '$',
        'PLN' => 'zł',
        'RUB' => '₽',
        'SAR' => 'ر.س',
        'SEK' => 'kr',
        'SGD' => '$',
        'THB' => '฿',
        'TRY' => '₺',
        'TZS' => 'TSh',
        'UGX' => 'USh',
        'USD' => '$',
        'XAF' => 'FCFA',
        'XOF' => 'CFA',
        'ZAR' => 'R',
        'ZMW' => 'ZK',
    ];

    /**
     * Resolve the currency associated with a visitor's public IP address.
     *
     * @return array{code: string, symbol: string}
     */
    public function resolve(?string $ip): array
    {
        $fallback = $this->currency(self::DEFAULT_CURRENCY_CODE);

        if (! $this->isLookupAvailable($ip)) {
            return $fallback;
        }

        $cacheKey = 'homepage.currency.'.hash('sha256', $ip);
        $cacheTtl = max(60, (int) config('services.ip_geolocation.cache_ttl', 86400));

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtl), function () use ($fallback, $ip): array {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout((float) config('services.ip_geolocation.connect_timeout', 0.5))
                    ->timeout((float) config('services.ip_geolocation.timeout', 1.5))
                    ->get($this->endpoint($ip));

                if (! $response->successful() || $response->json('success') === false) {
                    return $fallback;
                }

                $code = strtoupper(trim((string) $response->json('currency.code')));

                if (! preg_match('/^[A-Z]{3}$/', $code)) {
                    return $fallback;
                }

                $providerSymbol = trim(strip_tags((string) $response->json('currency.symbol')));

                return $this->currency($code, $providerSymbol);
            } catch (Throwable) {
                return $fallback;
            }
        });
    }

    /**
     * @return array{code: string, symbol: string}
     */
    private function currency(string $code, ?string $symbol = null): array
    {
        $normalizedCode = strtoupper($code);

        return [
            'code' => $normalizedCode,
            'symbol' => filled($symbol) ? mb_substr($symbol, 0, 12) : (self::CURRENCY_SYMBOLS[$normalizedCode] ?? $normalizedCode),
        ];
    }

    private function endpoint(string $ip): string
    {
        $template = (string) config('services.ip_geolocation.url', 'https://ipwho.is/{ip}');

        return str_contains($template, '{ip}')
            ? str_replace('{ip}', rawurlencode($ip), $template)
            : rtrim($template, '/').'/'.rawurlencode($ip);
    }

    private function isLookupAvailable(?string $ip): bool
    {
        return (bool) config('services.ip_geolocation.enabled', true)
            && is_string($ip)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
