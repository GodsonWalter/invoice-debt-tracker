<?php

namespace Database\Factories;

use App\Models\SmsConnection;
use App\Models\SmsConnectionAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsConnectionAudit>
 */
class SmsConnectionAuditFactory extends Factory
{
    protected $model = SmsConnectionAudit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sms_connection_id' => SmsConnection::factory(),
            'workspace_id' => null,
            'actor_user_id' => User::factory(),
            'event' => 'platform_sms_connection.updated',
            'changes' => [],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
        ];
    }
}
