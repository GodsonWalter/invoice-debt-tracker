<?php

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

/**
 * @return array{0: User, 1: Workspace, 2: Invoice}
 */
function createInvoiceEmailFixture(string $status = Invoice::STATUS_SENT, ?string $clientEmail = 'client@example.test'): array
{
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

test('workspace user can queue an invoice email', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);
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

        return str($html)->contains('/invoice/public/'.$invoice->public_token)
            && str($html)->contains('signature=')
            && ! str($html)->contains('/workspace/'.$invoice->workspace_id.'/invoices/'.$invoice->id);
    });

    expect($emailLog->refresh())
        ->status->toBe(InvoiceEmailLog::STATUS_SENT)
        ->sent_at->not->toBeNull();
});

test('draft invoices are not queued from the send action', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);
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
