<?php

use App\Http\Middleware\EnsureRouteWorkspaceMatchesActiveWorkspace;
use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * @return array{0: User, 1: Workspace, 2: Invoice}
 */
function createInvoiceForPaymentTest(string $status = 'draft'): array
{
    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Acme Workspace',
        'slug' => 'acme-workspace',
        'subdomain' => 'acme',
        'invoice_prefix' => 'INV',
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Acme Client',
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-0001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => $status,
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
    ]);

    return [$user, $workspace, $invoice];
}

test('a workspace user can record partial and final invoice payments', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);

    [$user, $workspace, $invoice] = createInvoiceForPaymentTest();

    $this->actingAs($user)
        ->post(route('invoices.payments.store', [$workspace, $invoice]), [
            'amount' => 400,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank transfer',
            'reference' => 'TXN-400',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh())
        ->status->toBe('partial')
        ->paid_amount->toBe(400.0)
        ->total_paid->toBe(400.0)
        ->remaining_balance->toBe(600.0)
        ->is_paid->toBeFalse()
        ->is_partially_paid->toBeTrue();

    expect($invoice->payments()->first())
        ->reference->toBe('TXN-400');

    $this->actingAs($user)
        ->post(route('invoices.payments.store', [$workspace, $invoice]), [
            'amount' => 600,
            'payment_date' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh())
        ->status->toBe('paid')
        ->paid_amount->toBe(1000.0)
        ->remaining_balance->toBe(0.0)
        ->is_paid->toBeTrue();
});

test('payment amount cannot exceed the invoice remaining balance', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);

    [$user, $workspace, $invoice] = createInvoiceForPaymentTest('sent');

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->post(route('invoices.payments.store', [$workspace, $invoice]), [
            'amount' => 1000.01,
            'payment_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('amount')
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh())
        ->status->toBe('sent')
        ->paid_amount->toBe(0.0)
        ->remaining_balance->toBe(1000.0);
});

test('repeated browser payment submissions are idempotent', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);

    [$user, $workspace, $invoice] = createInvoiceForPaymentTest('sent');
    $idempotencyKey = (string) Str::uuid();
    $payload = [
        'amount' => 400,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Bank transfer',
        'idempotency_key' => $idempotencyKey,
    ];

    $this->actingAs($user)
        ->post(route('invoices.payments.store', [$workspace, $invoice]), $payload)
        ->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->post(route('invoices.payments.store', [$workspace, $invoice]), $payload)
        ->assertSessionHasNoErrors();

    expect($invoice->payments()->where('idempotency_key', $idempotencyKey)->count())->toBe(1)
        ->and($invoice->refresh()->total_paid)->toBe(400.0)
        ->and($invoice->payments()->count())->toBe(1);
});

test('invoice updates cannot create a total below payments already recorded', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);

    [$user, $workspace, $invoice] = createInvoiceForPaymentTest('sent');
    $currency = Currency::create([
        'code' => 'USD',
        'symbol' => '$',
        'name' => 'US Dollar',
        'is_active' => true,
    ]);
    $invoice->update(['currency_id' => $currency->id]);
    $invoice->payments()->create([
        'workspace_id' => $workspace->id,
        'amount' => 600,
        'payment_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->from(route('invoices.edit', [$workspace, $invoice]))
        ->put(route('invoices.update', [$workspace, $invoice]), [
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $invoice->client_id,
            'currency_id' => $currency->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'sent',
            'tax_amount' => 0,
            'discount_amount' => 500,
            'items' => [[
                'item_name' => 'Reduced total',
                'description' => '',
                'quantity' => 1,
                'unit_price' => 500,
            ]],
        ])
        ->assertRedirect(route('invoices.edit', [$workspace, $invoice]))
        ->assertSessionHas('error', 'Failed to update invoice: The invoice total cannot be lower than payments already recorded.');

    expect($invoice->refresh()->total_amount)->toBe('1000.00')
        ->and($invoice->items()->count())->toBe(0);
});

test('invoice payment history shows payment audit details', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);

    [$user, $workspace, $invoice] = createInvoiceForPaymentTest('sent');

    $this->actingAs($user)
        ->post(route('invoices.payments.store', [$workspace, $invoice]), [
            'amount' => 250,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Card',
            'reference' => 'RCPT-250',
            'notes' => 'Customer paid by card.',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('invoices.show', [$workspace, $invoice]))
        ->assertOk()
        ->assertSee('Payment History')
        ->assertSee('Invoice Created')
        ->assertSee('Payment Received')
        ->assertSee('Card')
        ->assertSee('RCPT-250')
        ->assertSee('Customer paid by card.')
        ->assertSee('Remaining balance');
});
