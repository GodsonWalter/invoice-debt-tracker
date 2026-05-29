<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'symbol' => '$',
                'name' => 'US Dollar',
            ],
            [
                'code' => 'NGN',
                'symbol' => "\u{20A6}",
                'name' => 'Nigerian Naira',
            ],
            [
                'code' => 'EUR',
                'symbol' => "\u{20AC}",
                'name' => 'Euro',
            ],
            [
                'code' => 'GBP',
                'symbol' => "\u{00A3}",
                'name' => 'British Pound',
            ],
            [
                'code' => 'CAD',
                'symbol' => '$',
                'name' => 'Canadian Dollar',
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'symbol' => $currency['symbol'],
                    'name' => $currency['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
