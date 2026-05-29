<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class BusinessProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Workspace::query()->each(function (Workspace $workspace): void {
            BusinessProfile::updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'business_name' => $workspace->name,
                    'email' => 'billing@'.$workspace->subdomain.'.example',
                    'phone' => '+1 555 0200',
                    'address' => '100 '.$workspace->name.' Plaza',
                    'tax_id' => strtoupper($workspace->invoice_prefix).'-TAX-'.$workspace->id,
                    'city' => 'Lagos',
                    'state' => 'Lagos',
                    'postal_code' => '100001',
                    'country' => 'Nigeria',
                    'business_description' => 'Seeded business profile for invoice testing.',
                ],
            );
        });
    }
}
