<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\URL;

/**
 * @return array{0: Workspace, 1: Invoice}
 */
function createPublicInvoiceFixture(string $status = Invoice::STATUS_SENT): array
{
    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Public Workspace',
        'slug' => 'public-workspace',
        'subdomain' => 'public-workspace',
        'invoice_prefix' => 'PUB',
        'is_active' => true,
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Public Client',
        'email' => 'public-client@example.test',
        'address' => '12 Client Street',
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'PUB-2026-0001',
        'issue_date' => '2026-05-30',
        'due_date' => '2026-06-06',
        'status' => $status,
        'subtotal' => 1000,
        'tax_amount' => 100,
        'discount_amount' => 50,
        'total_amount' => 1050,
        'notes' => 'Public invoice note.',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Public Service',
        'description' => 'Public invoice access',
        'quantity' => 1,
        'unit_price' => 1000,
        'total_price' => 1000,
    ]);

    Payment::create([
        'workspace_id' => $workspace->id,
        'invoice_id' => $invoice->id,
        'amount' => 250,
        'payment_date' => '2026-05-31',
        'payment_method' => 'Bank transfer',
        'reference' => 'PUB-PAY-250',
    ]);

    return [$workspace, $invoice];
}

test('guest can view a public invoice by token', function () {
    [, $invoice] = createPublicInvoiceFixture();

    $this->get(URL::temporarySignedRoute('public.invoice.show', now()->addDays(30), ['token' => $invoice->public_token]))
        ->assertOk()
        ->assertSee('Invoice PUB-2026-0001')
        ->assertSee('Public Client')
        ->assertSee('Public Service')
        ->assertSee('Download PDF')
        ->assertSee('Print Invoice')
        ->assertSee('Payment History');

    expect($invoice->refresh()->viewed_at)->not->toBeNull();
});

test('guest can download a public invoice pdf by token', function () {
    [, $invoice] = createPublicInvoiceFixture();

    $this->get(URL::temporarySignedRoute('public.invoice.pdf', now()->addDays(30), ['token' => $invoice->public_token]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename=invoice-PUB-2026-0001.pdf');

    expect($invoice->refresh()->downloaded_at)->not->toBeNull();
});

test('guest can open public invoice print view by token', function () {
    [, $invoice] = createPublicInvoiceFixture();

    $this->get(URL::temporarySignedRoute('public.invoice.print', now()->addDays(30), ['token' => $invoice->public_token]))
        ->assertOk()
        ->assertSee('window.print')
        ->assertSee('Invoice PUB-2026-0001');

    expect($invoice->refresh()->printed_at)->not->toBeNull();
});

test('public invoice route does not expose invalid or draft invoices', function () {
    [, $invoice] = createPublicInvoiceFixture(Invoice::STATUS_DRAFT);

    $this->get(URL::temporarySignedRoute('public.invoice.show', now()->addDays(30), ['token' => $invoice->public_token]))
        ->assertNotFound();

    $this->get(URL::temporarySignedRoute('public.invoice.show', now()->addDays(30), ['token' => 'not-a-real-token']))
        ->assertNotFound();
});

test('public invoice routes reject unsigned or tampered URLs', function () {
    [, $invoice] = createPublicInvoiceFixture();

    $this->get(route('public.invoice.show', $invoice->public_token))->assertForbidden();
    $this->get(route('public.invoice.pdf', $invoice->public_token))->assertForbidden();
    $this->get(route('public.invoice.print', $invoice->public_token))->assertForbidden();

    $signedUrl = URL::temporarySignedRoute('public.invoice.show', now()->addDays(30), ['token' => $invoice->public_token]);
    $tamperedUrl = str_replace($invoice->public_token, 'not-a-real-token', $signedUrl);

    $this->get($tamperedUrl)->assertForbidden();
});
