<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\BusinessProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\Workspace;

function createInvoicePdfFixture(): array
{
    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Pdf Workspace',
        'slug' => 'pdf-workspace',
        'subdomain' => 'pdf-workspace',
        'invoice_prefix' => 'PDF',
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    BusinessProfile::create([
        'workspace_id' => $workspace->id,
        'business_name' => 'Pdf Studio',
        'email' => 'billing@pdf.test',
        'phone' => '+234 800 111 2222',
        'address' => '12 Test Street',
        'city' => 'Lagos',
        'country' => 'Nigeria',
        'tax_id' => 'TIN-PDF',
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Pdf Client',
        'email' => 'client@pdf.test',
        'address' => '8 Client Road',
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'PDF-2026-0001',
        'issue_date' => '2026-05-30',
        'due_date' => '2026-06-06',
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 1000,
        'tax_amount' => 100,
        'discount_amount' => 50,
        'total_amount' => 1050,
        'notes' => 'Thank you for your business.',
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Consulting',
        'description' => 'Implementation support',
        'quantity' => 2,
        'unit_price' => 500,
        'total_price' => 1000,
    ]);

    return [$user, $workspace, $invoice];
}

test('workspace user can download invoice pdf', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    [$user, $workspace, $invoice] = createInvoicePdfFixture();

    $this->actingAs($user)
        ->get(route('invoices.pdf', [$workspace, $invoice]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename=invoice-PDF-2026-0001.pdf');
});

test('invoice pdf cannot be downloaded from another workspace', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    [$user, , $invoice] = createInvoicePdfFixture();

    $otherWorkspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Other Pdf Workspace',
        'slug' => 'other-pdf-workspace',
        'subdomain' => 'other-pdf-workspace',
        'invoice_prefix' => 'OPDF',
        'is_active' => true,
    ]);

    $otherWorkspace->users()->attach($user->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.pdf', [$otherWorkspace, $invoice]))
        ->assertNotFound();
});
