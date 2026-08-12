<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'singleton_key' => 'global',
            'product_name' => 'IDT',
            'product_title' => 'Invoice and Debt Tracker',
            'tagline' => 'Invoice clarity for growing businesses.',
            'maintenance_banner_enabled' => false,
        ];
    }
}
