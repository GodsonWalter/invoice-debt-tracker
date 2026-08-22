<?php

use App\Jobs\SendWhatsAppReminderJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PlatformSetting;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppMessageLog;
use App\Models\Workspace;
use App\Services\PlatformConfigurationService;
use App\Services\ReminderContentService;
use App\Services\ReminderService;
use App\Services\WhatsAppCloudApiService;
use App\Services\WhatsAppConnectionService;
use App\Services\WhatsAppMessageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{user: User, workspace: Workspace, client: Client, invoice: Invoice, schedule: ReminderSchedule}
 */
function whatsappFixture(bool $withEmail = true): array
{
    $user = User::factory()->create(['role' => 'user']);
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'subdomain' => 'whatsapp-'.fake()->unique()->numerify('####'),
        'whatsapp_auto_reminders_enabled' => false,
        'whatsapp_architecture' => WhatsAppConnection::TYPE_TENANT,
    ]);
    $workspace->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'WhatsApp Customer',
        'email' => $withEmail ? 'customer@example.test' : null,
        'phone' => '+234 800 000 0000',
    ]);
    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'WHA-'.fake()->unique()->numerify('#####'),
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
        'item_name' => 'Reminder service',
        'description' => 'Automated invoice reminder service',
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

function whatsappUrl(Workspace $workspace, string $route, array $parameters = []): string
{
    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route($route, $parameters, false);
}

test('workspace owners can enable WhatsApp reminders and choose an architecture', function (): void {
    $fixture = whatsappFixture();
    $url = whatsappUrl($fixture['workspace'], 'whatsapp.settings.update', [$fixture['workspace']]);

    $this->actingAs($fixture['user'])
        ->put($url, [
            'whatsapp_auto_reminders_enabled' => true,
            'whatsapp_architecture' => WhatsAppConnection::TYPE_SHARED,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($fixture['workspace']->refresh())
        ->whatsapp_auto_reminders_enabled->toBeTrue()
        ->whatsapp_architecture->toBe(WhatsAppConnection::TYPE_SHARED);
});

test('non-management workspace members cannot change WhatsApp settings', function (): void {
    $fixture = whatsappFixture();
    $member = User::factory()->create();
    $fixture['workspace']->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);
    $url = whatsappUrl($fixture['workspace'], 'whatsapp.settings.update', [$fixture['workspace']]);

    $this->actingAs($member)
        ->put($url, [
            'whatsapp_auto_reminders_enabled' => true,
            'whatsapp_architecture' => WhatsAppConnection::TYPE_TENANT,
        ])
        ->assertForbidden();
});

test('tenant connection saves encrypted credentials and verifies the Meta connection', function (): void {
    config(['services.whatsapp.enabled' => true, 'services.whatsapp.allow_text_reminders' => true]);
    $fixture = whatsappFixture();
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/subscribed_apps')) {
            return Http::response(['success' => true]);
        }

        return Http::response([
            'display_phone_number' => '+2348000000000',
            'verified_name' => 'Tenant Business',
            'quality_rating' => 'GREEN',
            'status' => 'CONNECTED',
        ]);
    });
    $url = whatsappUrl($fixture['workspace'], 'whatsapp.connect', [$fixture['workspace']]);

    $this->actingAs($fixture['user'])
        ->post($url, [
            'business_portfolio_id' => 'business-1',
            'waba_id' => 'waba-1',
            'phone_number_id' => 'phone-number-id',
            'access_token' => 'secret-token',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $connection = WhatsAppConnection::query()->where('workspace_id', $fixture['workspace']->id)->firstOrFail();

    expect($connection)
        ->status->toBe(WhatsAppConnection::STATUS_CONNECTED)
        ->is_enabled->toBeTrue()
        ->verified_name->toBe('Tenant Business');
    expect($connection->access_token)->toBe('secret-token');
    expect($connection->getRawOriginal('access_token'))->not->toBe('secret-token');
});

test('automatic WhatsApp reminders are created for a phone-only client', function (): void {
    config(['services.whatsapp.enabled' => true]);
    Queue::fake();
    $fixture = whatsappFixture(withEmail: false);
    $fixture['workspace']->forceFill(['whatsapp_auto_reminders_enabled' => true])->save();
    WhatsAppConnection::factory()->create([
        'workspace_id' => $fixture['workspace']->id,
        'phone_number_id' => 'phone-'.fake()->unique()->numerify('#####'),
    ]);

    $summary = app(ReminderService::class)->process(now());
    $log = WhatsAppMessageLog::query()->firstOrFail();

    expect($summary['whatsapp_reminders_created'])->toBe(1)
        ->and($log->workspace_id)->toBe($fixture['workspace']->id)
        ->and($log->recipient_phone)->toBe('2348000000000')
        ->and($log->status)->toBe(WhatsAppMessageLog::STATUS_QUEUED);
    Queue::assertPushed(SendWhatsAppReminderJob::class, 1);
});

test('WhatsApp reminder jobs send the rendered email reminder content', function (): void {
    config(['services.whatsapp.enabled' => true, 'services.whatsapp.allow_text_reminders' => true]);
    Http::fake(['*messages' => Http::response(['messages' => [['id' => 'wamid.test']]])]);
    $fixture = whatsappFixture();
    $fixture['workspace']->forceFill(['whatsapp_auto_reminders_enabled' => true])->save();
    $connection = WhatsAppConnection::factory()->create([
        'workspace_id' => $fixture['workspace']->id,
        'phone_number_id' => 'phone-'.fake()->unique()->numerify('#####'),
    ]);
    $log = WhatsAppMessageLog::create([
        'workspace_id' => $fixture['workspace']->id,
        'whatsapp_connection_id' => $connection->id,
        'invoice_id' => $fixture['invoice']->id,
        'reminder_schedule_id' => $fixture['schedule']->id,
        'recipient_phone' => '2348000000000',
        'idempotency_key' => 'test:'.$fixture['invoice']->id,
        'status' => WhatsAppMessageLog::STATUS_QUEUED,
    ]);

    (new SendWhatsAppReminderJob($log->id))->handle(
        app(WhatsAppMessageService::class),
        app(WhatsAppConnectionService::class),
        app(WhatsAppCloudApiService::class),
        app(ReminderContentService::class),
    );

    Http::assertSent(function ($request): bool {
        return str_ends_with($request->url(), '/messages')
            && $request['type'] === 'text'
            && str_contains($request['text']['body'], 'invoice');
    });
    expect($log->refresh())
        ->status->toBe(WhatsAppMessageLog::STATUS_SENT)
        ->meta_message_id->toBe('wamid.test')
        ->sent_at->not->toBeNull();
});

test('WhatsApp webhook updates delivery status and is signature protected', function (): void {
    config(['services.whatsapp.app_secret' => 'webhook-secret']);
    $fixture = whatsappFixture();
    $log = WhatsAppMessageLog::create([
        'workspace_id' => $fixture['workspace']->id,
        'invoice_id' => $fixture['invoice']->id,
        'recipient_phone' => '2348000000000',
        'idempotency_key' => 'webhook:'.$fixture['invoice']->id,
        'meta_message_id' => 'wamid.webhook',
        'status' => WhatsAppMessageLog::STATUS_SENT,
    ]);
    $payload = json_encode([
        'entry' => [[
            'id' => 'waba-1',
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => 'phone-1'],
                    'statuses' => [['id' => 'wamid.webhook', 'status' => 'delivered', 'timestamp' => (string) now()->timestamp]],
                ],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $payload, 'webhook-secret');

    $this->call('POST', '/webhooks/whatsapp/meta', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)
        ->assertOk();

    expect($log->refresh())
        ->status->toBe(WhatsAppMessageLog::STATUS_DELIVERED)
        ->delivered_at->not->toBeNull();
});

test('platform WhatsApp management is restricted to platform management roles', function (): void {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)
        ->get(route('platform.whatsapp.index'))
        ->assertForbidden();

    $platformOwner = User::factory()->create(['role' => 'owner']);
    (PlatformSetting::query()->first() ?? PlatformSetting::factory()->create())->update(['product_name' => 'LedgerPro']);
    app(PlatformConfigurationService::class)->forgetCache();

    $this->actingAs($platformOwner)
        ->get(route('platform.whatsapp.index'))
        ->assertOk()
        ->assertSee('Shared LedgerPro connection')
        ->assertDontSee('Shared IDT connection');
});
