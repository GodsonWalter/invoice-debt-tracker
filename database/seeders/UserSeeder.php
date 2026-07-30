<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seedPassword = config('seeding.user_password');

        if (app()->environment('production') && blank($seedPassword)) {
            throw new \RuntimeException('SEED_USER_PASSWORD must be set before seeding a production environment.');
        }

        $seedPassword ??= 'password';

        $users = [
            [
                'name' => 'Amina Bello',
                'email' => 'amina@example.com',
                'role' => 'owner',
            ],
            [
                'name' => 'Daniel Reed',
                'email' => 'daniel@example.com',
                'role' => 'admin',
            ],
            [
                'name' => 'Grace Okafor',
                'email' => 'grace@example.com',
                'role' => 'manager',
            ],
            [
                'name' => 'Maya Chen',
                'email' => 'maya@example.com',
                'role' => 'staff',
            ],
            [
                'name' => 'Omar Hassan',
                'email' => 'omar@example.com',
                'role' => 'user',
            ],
        ];

        foreach ($users as $userData) {
            $defaultCurrency = Currency::query()
                ->where('code', $userData['email'] === 'amina@example.com' ? 'NGN' : 'USD')
                ->first();

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($seedPassword),
                    'role' => $userData['role'],
                    'default_currency_id' => $defaultCurrency?->id,
                ],
            );

            $user->forceFill([
                'email_verified_at' => now(),
                'is_active' => true,
            ])->save();
        }
    }
}
