<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;

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
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    [$user, $workspace, $invoice] = createInvoiceForPaymentTest();

    $this->actingAs($user)
        ->post(route('invoices.payments.store', [$workspace, $invoice]), [
            'amount' => 400,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank transfer',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh())
        ->status->toBe('sent')
        ->paid_amount->toBe(400.0)
        ->remaining_balance->toBe(600.0)
        ->is_paid->toBeFalse();

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
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

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
