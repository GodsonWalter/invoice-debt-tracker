<?php

namespace Database\Factories;

use App\Models\WhatsAppConnection;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppConnection>
 */
class WhatsAppConnectionFactory extends Factory
{
    protected $model = WhatsAppConnection::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'connection_type' => WhatsAppConnection::TYPE_TENANT,
            'business_portfolio_id' => fake()->numerify('business-########'),
            'waba_id' => fake()->numerify('waba-########'),
            'phone_number_id' => fake()->unique()->numerify('phone-########'),
            'display_phone_number' => '+2348000000000',
            'verified_name' => fake()->company(),
            'access_token' => 'test-access-token',
            'status' => WhatsAppConnection::STATUS_CONNECTED,
            'is_enabled' => true,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => null,
            'connection_type' => WhatsAppConnection::TYPE_SHARED,
        ]);
    }
}
