<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => 'password',
                    'role' => $userData['role'],
                ],
            );

            $user->forceFill([
                'email_verified_at' => now(),
                'is_active' => true,
            ])->save();
        }
    }
}
