<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->orderBy('id')->take(5)->get();

        $workspaces = [
            [
                'name' => 'Waltech ICT',
                'slug' => 'waltech-ict',
                'subdomain' => 'waltechict',
                'metadata' => ['timezone' => 'UTC', 'currency' => 'USD'],
                'is_active' => true,
                'owner_email' => 'walter.godson.nazike@gmail.com',
            ],
            [
                'name' => 'Blue Horizon',
                'slug' => 'blue-horizon',
                'subdomain' => 'blue',
                'metadata' => ['timezone' => 'UTC', 'currency' => 'EUR'],
                'is_active' => true,
                'owner_email' => 'daniel@example.com',
            ],
            [
                'name' => 'Green Fields',
                'slug' => 'green-fields',
                'subdomain' => 'green',
                'metadata' => ['timezone' => 'UTC', 'currency' => 'GBP'],
                'is_active' => true,
                'owner_email' => 'grace@example.com',
            ],
            [
                'name' => 'Silver Line',
                'slug' => 'silver-line',
                'subdomain' => 'silver',
                'metadata' => ['timezone' => 'UTC', 'currency' => 'CAD'],
                'is_active' => true,
                'owner_email' => 'maya@example.com',
            ],
        ];

        foreach ($workspaces as $workspaceData) {
            $owner = User::query()
                ->with('defaultCurrency')
                ->where('email', $workspaceData['owner_email'])
                ->first() ?? $users->first();

            $currency = $owner?->defaultCurrency
                ?? Currency::query()->where('code', 'USD')->first();

            unset($workspaceData['owner_email']);

            $workspaceData['owner_id'] = $owner?->id;
            $workspaceData['currency_id'] = $currency?->id;
            $created = Workspace::updateOrCreate(
                ['slug' => $workspaceData['slug']],
                $workspaceData
            );

            if ($owner) {
                $created->users()->syncWithoutDetaching([
                    $owner->id => [
                        'role' => 'owner',
                        'is_active' => true,
                    ],
                ]);

                $created->users()->updateExistingPivot($owner->id, [
                    'role' => 'owner',
                    'is_active' => true,
                ]);
            }

            foreach ($users as $user) {
                if ($owner && $user->is($owner)) {
                    continue;
                }

                $role = $user->role === 'admin' ? 'admin' : 'member';

                $created->users()->syncWithoutDetaching([
                    $user->id => [
                        'role' => $role,
                        'is_active' => true,
                    ],
                ]);

                $created->users()->updateExistingPivot($user->id, [
                    'role' => $role,
                    'is_active' => true,
                ]);
            }
        }
    }
}
