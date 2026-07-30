<?php

use App\Http\Middleware\EnsureRouteWorkspaceMatchesActiveWorkspace;
use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Jobs\SendReminderEmailJob;
use App\Mail\ReminderMail;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReminderLog;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ReminderService;
use App\Services\TemplateRenderer;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{0: User, 1: Workspace, 2: Client, 3: Invoice}
 */
function createEmailTemplateFixture(): array
{
    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Template Workspace',
        'slug' => 'template-workspace',
        'subdomain' => 'template-workspace',
        'invoice_prefix' => 'TPL',
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Template Client',
        'email' => 'template-client@example.test',
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'TPL-2026-0001',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-06-20',
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 1250,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1250,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Template work',
        'description' => 'Template test invoice item',
        'quantity' => 1,
        'unit_price' => 1250,
        'total_price' => 1250,
    ]);

    return [$user, $workspace, $client, $invoice];
}

test('template renderer replaces supported placeholders', function () {
    [, , $client, $invoice] = createEmailTemplateFixture();

    $rendered = app(TemplateRenderer::class)->render(
        'Hello {{client_name}}, invoice {{invoice_number}} has {{balance_due}} due on {{due_date}}.',
        $invoice,
    );

    expect($rendered)
        ->toContain($client->name)
        ->toContain($invoice->invoice_number)
        ->toContain('1,250.00')
        ->toContain('Jun 20, 2026');
});

test('workspace user can create an email template', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        ResolveWorkspace::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
    ]);

    [$user, $workspace] = createEmailTemplateFixture();

    $this->actingAs($user)
        ->post(route('email-templates.store', $workspace), [
            'name' => 'Invoice Reminder',
            'type' => EmailTemplate::TYPE_BEFORE_DUE,
            'subject' => 'Invoice {{invoice_number}} is due soon',
            'body' => 'Hello {{client_name}}, please pay {{balance_due}}.',
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($workspace->emailTemplates()->where('type', EmailTemplate::TYPE_BEFORE_DUE)->exists())->toBeTrue();
});

test('duplicate template types are rejected per workspace', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        ResolveWorkspace::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
    ]);

    [$user, $workspace] = createEmailTemplateFixture();

    EmailTemplate::create([
        'workspace_id' => $workspace->id,
        'name' => 'Existing Before Due',
        'type' => EmailTemplate::TYPE_BEFORE_DUE,
        'subject' => 'Existing',
        'body' => 'Existing',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('email-templates.store', $workspace), [
            'name' => 'Duplicate Before Due',
            'type' => EmailTemplate::TYPE_BEFORE_DUE,
            'subject' => 'Duplicate',
            'body' => 'Duplicate',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('type');

    expect($workspace->emailTemplates()->where('type', EmailTemplate::TYPE_BEFORE_DUE)->count())->toBe(1);
});

test('template preview renders selected invoice without sending mail', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        ResolveWorkspace::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
    ]);
    Mail::fake();

    [$user, $workspace, , $invoice] = createEmailTemplateFixture();

    $this->actingAs($user)
        ->from(route('email-templates.create', $workspace))
        ->post(route('email-templates.preview', $workspace), [
            'name' => 'Preview Template',
            'type' => EmailTemplate::TYPE_DUE_TODAY,
            'subject' => 'Invoice {{invoice_number}} preview',
            'body' => 'Hello {{client_name}}, pay {{balance_due}}.',
            'invoice_id' => $invoice->id,
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('template_preview')
        ->assertRedirect(route('email-templates.create', $workspace));

    Mail::assertNothingSent();

    $preview = session('template_preview');

    expect($preview['subject'])->toBe('Invoice TPL-2026-0001 preview');
    expect($preview['body'])->toContain('Template Client');
});

test('workspace members cannot create or update email templates', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        ResolveWorkspace::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
    ]);

    [, $workspace] = createEmailTemplateFixture();
    $member = User::factory()->create();
    $workspace->users()->attach($member->id, [
        'role' => 'member',
        'is_active' => true,
    ]);

    $payload = [
        'name' => 'Member Template',
        'type' => EmailTemplate::TYPE_BEFORE_DUE,
        'subject' => 'Member subject',
        'body' => 'Member body',
        'is_active' => '1',
    ];

    $this->actingAs($member)
        ->post(route('email-templates.store', $workspace), $payload)
        ->assertForbidden();

    $template = EmailTemplate::create([
        'workspace_id' => $workspace->id,
        'name' => 'Existing Template',
        'type' => EmailTemplate::TYPE_BEFORE_DUE,
        'subject' => 'Existing subject',
        'body' => 'Existing body',
        'is_active' => true,
    ]);

    $this->actingAs($member)
        ->put(route('email-templates.update', [$workspace, $template]), $payload)
        ->assertForbidden();
});

test('reminder email job uses workspace email template', function () {
    Mail::fake();

    [, $workspace, $client, $invoice] = createEmailTemplateFixture();

    $schedule = ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => '3 Days Before Due',
        'days_offset' => 3,
        'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
        'is_active' => true,
    ]);

    EmailTemplate::create([
        'workspace_id' => $workspace->id,
        'name' => 'Before Due Custom',
        'type' => EmailTemplate::TYPE_BEFORE_DUE,
        'subject' => 'Custom subject for {{invoice_number}}',
        'body' => 'Custom body for {{client_name}} with {{balance_due}} due.',
        'is_active' => true,
    ]);

    $reminderLog = ReminderLog::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'reminder_schedule_id' => $schedule->id,
        'recipient_email' => $client->email,
        'status' => ReminderLog::STATUS_PENDING,
    ]);

    (new SendReminderEmailJob($reminderLog->id))->handle(app(ReminderService::class), app(TemplateRenderer::class));

    Mail::assertSent(ReminderMail::class, function (ReminderMail $mail): bool {
        return $mail->renderedSubject === 'Custom subject for TPL-2026-0001'
            && str_contains($mail->renderedBody, 'Custom body for Template Client');
    });
});
