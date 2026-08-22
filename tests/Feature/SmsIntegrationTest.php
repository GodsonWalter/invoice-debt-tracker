<?php

use App\Jobs\SendSmsReminderJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReminderSchedule;
use App\Models\SmsConnection;
use App\Models\SmsMessageLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ReminderService;
use App\Services\SmsConnectionService;
use App\Services\SmsMessageService;
use App\Services\SmsProviderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{user: User, workspace: Workspace, client: Client, invoice: Invoice, schedule: ReminderSchedule}
 */
function smsFixture(bool $withEmail = true): array
{
    $user = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'sms_auto_reminders_enabled' => false,
        'sms_architecture' => SmsConnection::TYPE_WORKSPACE,
    ]);
    $workspace->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'SMS Customer',
        'email' => $withEmail ? 'sms-customer@example.test' : null,
        'phone' => '+234 800 000 0000',
    ]);
    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'SMS-'.fake()->unique()->numerify('#####'),
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 100,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'SMS reminder service',
        'description' => 'SMS reminder test item',
        'quantity' => 1,
        'unit_price' => 100,
        'total_price' => 100,
    ]);
    $schedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Before due',
        'days_offset' => 3,
        'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
        'is_active' => true,
    ]);

    return compact('user', 'workspace', 'client', 'invoice', 'schedule');
}

function smsUrl(Workspace $workspace, string $route, array $parameters = []): string
{
    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route($route, $parameters, false);
}

function smsConnectionFor(Workspace $workspace): SmsConnection
{
    return SmsConnection::factory()->create([
        'workspace_id' => $workspace->id,
        'connection_type' => SmsConnection::TYPE_WORKSPACE,
    ]);
}

test('workspace owners can choose either SMS architecture while members cannot change it', function (): void {
    $fixture = smsFixture();
    $url = smsUrl($fixture['workspace'], 'sms.settings.update', [$fixture['workspace']]);

    $this->actingAs($fixture['user'])
        ->put($url, [
            'sms_auto_reminders_enabled' => true,
            'sms_architecture' => SmsConnection::TYPE_SHARED,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($fixture['workspace']->refresh())
        ->sms_auto_reminders_enabled->toBeTrue()
        ->sms_architecture->toBe(SmsConnection::TYPE_SHARED);

    $member = User::factory()->create();
    $fixture['workspace']->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);

    $this->actingAs($member)
        ->put($url, [
            'sms_auto_reminders_enabled' => false,
            'sms_architecture' => SmsConnection::TYPE_WORKSPACE,
        ])
        ->assertForbidden();
});

test('workspace SMS management page renders the selected connection mode', function (): void {
    $fixture = smsFixture();
    $url = smsUrl($fixture['workspace'], 'sms.index', [$fixture['workspace']]);

    $this->actingAs($fixture['user'])
        ->get($url)
        ->assertOk()
        ->assertSee('Workspace-owned SMS connection')
        ->assertSee('Enable automatic SMS reminders');

    $fixture['workspace']->forceFill(['sms_architecture' => SmsConnection::TYPE_SHARED])->save();

    $this->actingAs($fixture['user'])
        ->get($url)
        ->assertOk()
        ->assertDontSee('Credentials are encrypted at rest.');
});

test('workspace SMS credentials are encrypted and verified before activation', function (): void {
    config(['services.sms.enabled' => true]);
    Http::fake(['*2010-04-01/Accounts/*' => Http::response(['sid' => 'AC-test', 'status' => 'active'])]);
    $fixture = smsFixture();

    $this->actingAs($fixture['user'])
        ->post(smsUrl($fixture['workspace'], 'sms.connect', [$fixture['workspace']]), [
            'provider' => 'twilio',
            'provider_account_id' => 'AC-test',
            'sender' => '+15551234567',
            'auth_token' => 'secret-sms-token',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $connection = SmsConnection::query()->where('workspace_id', $fixture['workspace']->id)->firstOrFail();

    expect($connection)
        ->status->toBe(SmsConnection::STATUS_CONNECTED)
        ->is_enabled->toBeTrue()
        ->auth_token->toBe('secret-sms-token');
    expect($connection->getRawOriginal('auth_token'))->not->toBe('secret-sms-token');
});

test('automatic SMS reminders are queued for a phone-only client', function (): void {
    config(['services.sms.enabled' => true]);
    Queue::fake();
    $fixture = smsFixture(withEmail: false);
    $fixture['workspace']->forceFill(['sms_auto_reminders_enabled' => true])->save();
    smsConnectionFor($fixture['workspace']);

    $summary = app(ReminderService::class)->process(now());
    $log = SmsMessageLog::query()->firstOrFail();

    expect($summary['sms_reminders_created'])->toBe(1)
        ->and($log->workspace_id)->toBe($fixture['workspace']->id)
        ->and($log->recipient_phone)->toBe('2348000000000')
        ->and($log->status)->toBe(SmsMessageLog::STATUS_QUEUED);
    Queue::assertPushed(SendSmsReminderJob::class, 1);
});

test('SMS reminder jobs send the rendered email reminder content', function (): void {
    config(['services.sms.enabled' => true]);
    Http::fake(['*Messages.json' => Http::response(['sid' => 'SM-test', 'status' => 'queued'])]);
    $fixture = smsFixture();
    $fixture['workspace']->forceFill(['sms_auto_reminders_enabled' => true])->save();
    $connection = smsConnectionFor($fixture['workspace']);
    $log = SmsMessageLog::create([
        'workspace_id' => $fixture['workspace']->id,
        'sms_connection_id' => $connection->id,
        'invoice_id' => $fixture['invoice']->id,
        'reminder_schedule_id' => $fixture['schedule']->id,
        'recipient_phone' => '2348000000000',
        'idempotency_key' => 'test:'.$fixture['invoice']->id,
        'status' => SmsMessageLog::STATUS_QUEUED,
        'message_type' => 'reminder',
    ]);

    (new SendSmsReminderJob($log->id))->handle(
        app(SmsMessageService::class),
        app(SmsConnectionService::class),
        app(SmsProviderService::class),
    );

    Http::assertSent(function ($request): bool {
        return str_ends_with($request->url(), '/Messages.json')
            && $request['To'] === '+2348000000000'
            && str_contains($request['Body'], 'invoice');
    });
    expect($log->refresh())
        ->status->toBe(SmsMessageLog::STATUS_SENT)
        ->provider_message_id->toBe('SM-test')
        ->sent_at->not->toBeNull();
});

test('workspace users can queue an invoice SMS from the invoice actions', function (): void {
    config(['services.sms.enabled' => true]);
    Queue::fake();
    $fixture = smsFixture();
    smsConnectionFor($fixture['workspace']);

    $this->actingAs($fixture['user'])
        ->post(smsUrl($fixture['workspace'], 'invoices.send-sms', [$fixture['workspace'], $fixture['invoice']]))
        ->assertRedirect(smsUrl($fixture['workspace'], 'invoices.show', [$fixture['workspace'], $fixture['invoice']]))
        ->assertSessionHasNoErrors();

    expect(SmsMessageLog::query()->where('message_type', 'invoice')->count())->toBe(1);
    Queue::assertPushed(SendSmsReminderJob::class, 1);
});

test('SMS webhook updates delivery status and rejects invalid signatures', function (): void {
    $fixture = smsFixture();
    $connection = smsConnectionFor($fixture['workspace']);
    $log = SmsMessageLog::create([
        'workspace_id' => $fixture['workspace']->id,
        'sms_connection_id' => $connection->id,
        'invoice_id' => $fixture['invoice']->id,
        'recipient_phone' => '2348000000000',
        'idempotency_key' => 'webhook:'.$fixture['invoice']->id,
        'provider_message_id' => 'SM-webhook',
        'status' => SmsMessageLog::STATUS_SENT,
    ]);
    $url = 'http://localhost/webhooks/sms/twilio';
    config(['services.sms.webhook_url' => $url]);
    $parameters = [
        'AccountSid' => $connection->provider_account_id,
        'MessageSid' => 'SM-webhook',
        'MessageStatus' => 'delivered',
    ];
    ksort($parameters);
    $data = $url;
    foreach ($parameters as $key => $value) {
        $data .= $key.$value;
    }
    $signature = base64_encode(hash_hmac('sha1', $data, 'test-auth-token', true));

    $this->post('/webhooks/sms/twilio', $parameters, ['X-Twilio-Signature' => $signature])
        ->assertOk();

    expect($log->refresh())
        ->status->toBe(SmsMessageLog::STATUS_DELIVERED)
        ->delivered_at->not->toBeNull();

    $this->post('/webhooks/sms/twilio', $parameters, ['X-Twilio-Signature' => 'invalid'])
        ->assertForbidden();
});

test('platform SMS management is restricted to platform management roles', function (): void {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->get(route('platform.sms.index'))
        ->assertForbidden();

    $platformOwner = User::factory()->create(['role' => 'owner']);

    $this->actingAs($platformOwner)
        ->get(route('platform.sms.index'))
        ->assertOk()
        ->assertSee('Shared')
        ->assertSee('SMS connection');
});
