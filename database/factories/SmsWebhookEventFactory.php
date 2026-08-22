<?php

namespace Database\Factories;

use App\Models\SmsWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsWebhookEvent>
 */
class SmsWebhookEventFactory extends Factory
{
    protected $model = SmsWebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dedupe_key' => fake()->unique()->sha256(),
            'provider' => 'twilio',
            'provider_account_id' => 'AC123456789',
            'provider_message_id' => 'SM'.fake()->unique()->numerify('###############'),
            'event_type' => 'message.status',
            'payload' => [],
            'processed_at' => now(),
        ];
    }
}
