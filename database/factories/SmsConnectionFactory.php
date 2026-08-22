<?php

namespace Database\Factories;

use App\Models\SmsConnection;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsConnection>
 */
class SmsConnectionFactory extends Factory
{
    protected $model = SmsConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'connection_type' => SmsConnection::TYPE_SHARED,
            'provider' => 'twilio',
            'provider_account_id' => fake()->unique()->bothify('AC########################'),
            'sender' => '+15551234567',
            'messaging_service_id' => null,
            'auth_token' => 'test-auth-token',
            'status' => SmsConnection::STATUS_CONNECTED,
            'is_enabled' => true,
        ];
    }

    public function workspace(): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => Workspace::factory(),
            'connection_type' => SmsConnection::TYPE_WORKSPACE,
        ]);
    }
}
