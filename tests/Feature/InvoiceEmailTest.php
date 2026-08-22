<?php

use App\Http\Middleware\EnsureRouteWorkspaceMatchesActiveWorkspace;
use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Jobs\SendInvoiceMailJob;
use App\Mail\InvoiceMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\Workspace;
use App\Services\InvoiceEmailService;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/**
 * @return array{0: User, 1: Workspace, 2: Invoice}
 */
function createInvoiceEmailFixture(
    string $status = Invoice::STATUS_SENT,
    ?string $clientEmail = 'client@example.test',
    ?string $clientPhone = null,
): array {
    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Email Workspace',
        'slug' => 'email-workspace',
        'subdomain' => 'email-workspace',
        'invoice_prefix' => 'EML',
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Email Client',
        'email' => $clientEmail,
        'phone' => $clientPhone,
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'EML-2026-0001',
        'issue_date' => '2026-05-30',
        'due_date' => '2026-06-06',
        'status' => $status,
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Implementation',
        'description' => 'Invoice email implementation',
        'quantity' => 1,
        'unit_price' => 1000,
        'total_price' => 1000,
    ]);

    return [$user, $workspace, $invoice];
}

test('invoice list and details expose separate mail and WhatsApp actions', function (): void {
    [$user, $workspace, $invoice] = createInvoiceEmailFixture(
        clientPhone: '+234 800 000 0000',
    );
    $host = 'http://'.$workspace->subdomain.'.'.config('app.base_domain');

    $this->actingAs($user)
        ->get($host.route('invoices.index', $workspace, false))
        ->assertOk()
        ->assertSee('Send to Mail')
        ->assertSee('bi bi-envelope', false)
        ->assertSee('Send to WhatsApp')
        ->assertSee(route('invoices.send-whatsapp', [$workspace, $invoice], false), false)
        ->assertSee('Send by SMS')
        ->assertSee(route('invoices.send-sms', [$workspace, $invoice], false), false);

    $this->actingAs($user)
        ->get($host.route('invoices.show', [$workspace, $invoice], false))
        ->assertOk()
        ->assertSee('Send to Mail')
        ->assertSee('bi bi-envelope', false)
        ->assertSee('Send to WhatsApp')
        ->assertSee('Send by SMS');
});

test('workspace user can open a secure invoice share message in WhatsApp', function (): void {
    [$user, $workspace, $invoice] = createInvoiceEmailFixture(
        clientPhone: '+234 800 000 0000',
    );
    $url = 'http://'.$workspace->subdomain.'.'.config('app.base_domain')
        .route('invoices.send-whatsapp', [$workspace, $invoice], false);

    $response = $this->actingAs($user)
        ->post($url);

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');

    expect($location)
        ->toStartWith('https://wa.me/2348000000000?text=')
        ->and(urldecode($location))
        ->toContain('EML-2026-0001')
        ->toContain("View your invoice here:\nhttp://")
        ->toContain('/invoice/public/')
        ->toContain('signature=');
});

test('workspace user can queue an invoice email', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);
    Queue::fake();

    [$user, $workspace, $invoice] = createInvoiceEmailFixture();

    $this->actingAs($user)
        ->post(route('invoices.send', [$workspace, $invoice]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    $emailLog = InvoiceEmailLog::query()->first();

    expect($emailLog)
        ->not->toBeNull()
        ->workspace_id->toBe($workspace->id)
        ->invoice_id->toBe($invoice->id)
        ->sent_by->toBe($user->id)
        ->recipient_email->toBe('client@example.test')
        ->status->toBe(InvoiceEmailLog::STATUS_PENDING);

    Queue::assertPushed(SendInvoiceMailJob::class);
});

test('invoice email job sends mail and marks log as sent', function () {
    Mail::fake();

    app()->bind(InvoicePdfService::class, fn (): InvoicePdfService => new class extends InvoicePdfService
    {
        public function content(Invoice $invoice): string
        {
            return '%PDF-1.4 test invoice';
        }

        public function filename(Invoice $invoice): string
        {
            return 'invoice-'.$invoice->invoice_number.'.pdf';
        }
    });

    [$user, $workspace, $invoice] = createInvoiceEmailFixture();

    $invoice = app(InvoiceEmailService::class)->invoiceForEmail($workspace, $invoice);

    $emailLog = InvoiceEmailLog::create([
        'invoice_id' => $invoice->id,
        'workspace_id' => $workspace->id,
        'sent_by' => $user->id,
        'recipient_email' => $invoice->client->email,
        'subject' => app(InvoiceEmailService::class)->subject($invoice),
        'status' => InvoiceEmailLog::STATUS_PENDING,
    ]);

    (new SendInvoiceMailJob($emailLog->id))->handle(app(InvoiceEmailService::class));

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice): bool {
        $html = $mail->render();
        $content = $mail->content();

        return str($html)->contains('/invoice/public/'.$invoice->public_token)
            && str($html)->contains('signature=')
            && ! str($html)->contains('/workspace/'.$invoice->workspace_id.'/invoices/'.$invoice->id)
            && array_key_exists('platformSettings', $content->with);
    });

    expect($emailLog->refresh())
        ->status->toBe(InvoiceEmailLog::STATUS_SENT)
        ->sent_at->not->toBeNull();
});

test('draft invoices are not queued from the send action', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);
    Queue::fake();

    [$user, $workspace, $invoice] = createInvoiceEmailFixture(Invoice::STATUS_DRAFT);

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->post(route('invoices.send', [$workspace, $invoice]))
        ->assertSessionHasErrors('invoice')
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect(InvoiceEmailLog::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('queued invoice emails do not send after the workspace is deactivated', function () {
    Mail::fake();

    [$user, $workspace, $invoice] = createInvoiceEmailFixture();
    $workspace->update(['is_active' => false]);
    $emailLog = InvoiceEmailLog::create([
        'invoice_id' => $invoice->id,
        'workspace_id' => $workspace->id,
        'sent_by' => $user->id,
        'recipient_email' => $invoice->client->email,
        'subject' => 'Invoice '.$invoice->invoice_number,
        'status' => InvoiceEmailLog::STATUS_PENDING,
    ]);

    expect(fn () => (new SendInvoiceMailJob($emailLog->id))->handle(app(InvoiceEmailService::class)))
        ->toThrow(ValidationException::class);

    Mail::assertNothingSent();
});
