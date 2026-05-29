<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [
            [
                'name' => 'Northwind Retail',
                'email' => 'billing@northwind.example',
                'phone' => '+1 555 0101',
                'address' => '12 Market Street',
            ],
            [
                'name' => 'Summit Logistics',
                'email' => 'accounts@summit.example',
                'phone' => '+1 555 0102',
                'address' => '48 Harbor Road',
            ],
            [
                'name' => 'Bright Labs',
                'email' => 'finance@brightlabs.example',
                'phone' => '+1 555 0103',
                'address' => '7 Innovation Avenue',
            ],
            [
                'name' => 'Cedar Foods',
                'email' => 'payables@cedarfoods.example',
                'phone' => '+1 555 0104',
                'address' => '90 Orchard Lane',
            ],
            [
                'name' => 'Urban Nest',
                'email' => 'hello@urbannest.example',
                'phone' => '+1 555 0105',
                'address' => '31 Skyline Boulevard',
            ],
        ];

        Workspace::query()->each(function (Workspace $workspace) use ($clients): void {
            foreach ($clients as $clientData) {
                $workspace->clients()->updateOrCreate(
                    ['email' => $workspace->slug.'.'.$clientData['email']],
                    [
                        'name' => $clientData['name'],
                        'phone' => $clientData['phone'],
                        'address' => $clientData['address'],
                        'notes' => 'Seeded client for '.$workspace->name.'.',
                    ],
                );
            }
        });
    }
}
