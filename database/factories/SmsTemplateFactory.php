<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\SmsConnection;
use App\Models\SmsTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsTemplate>
 */
class SmsTemplateFactory extends Factory
{
    protected $model = SmsTemplate::class;

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
            'type' => EmailTemplate::TYPE_BEFORE_DUE,
            'body' => 'Hello {{client_name}}, invoice {{invoice_number}} is due on {{due_date}}. Balance: {{balance_due}}.',
            'is_active' => true,
        ];
    }
}
