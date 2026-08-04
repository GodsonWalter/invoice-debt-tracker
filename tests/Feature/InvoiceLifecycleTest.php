<?php

use App\Http\Middleware\EnsureRouteWorkspaceMatchesActiveWorkspace;
use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PaymentService;
use App\Services\ReminderService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

function createInvoiceLifecycleFixture(string $status = Invoice::STATUS_DRAFT): array
{
    $user = User::factory()->create();
    $currency = Currency::create([
        'code' => 'USD',
        'symbol' => '$',
        'name' => 'US Dollar',
        'is_active' => true,
    ]);
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Lifecycle Workspace',
        'slug' => 'lifecycle-workspace',
        'subdomain' => 'lifecycle-workspace',
        'invoice_prefix' => 'LCY',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);
    $workspace->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);
    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Lifecycle Client',
        'email' => 'client@example.test',
    ]);
    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'currency_id' => $currency->id,
        'invoice_number' => 'LCY-2026-0001',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-06-30',
        'status' => $status,
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Lifecycle service',
        'description' => 'Lifecycle test item',
        'quantity' => 1,
        'unit_price' => 1000,
        'total_price' => 1000,
    ]);

    return [$user, $workspace, $client, $currency, $invoice];
}

beforeEach(function (): void {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);
});

test('draft invoices can be edited and audited lifecycle actions are tenant scoped', function (): void {
    [$user, $workspace, $client, $currency, $invoice] = createInvoiceLifecycleFixture();

    $this->actingAs($user)
        ->put(route('invoices.update', [$workspace, $invoice]), [
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'issue_date' => '2026-06-02',
            'due_date' => '2026-07-01',
            'status' => Invoice::STATUS_DRAFT,
            'tax_amount' => 100,
            'discount_amount' => 0,
            'notes' => 'Updated draft',
            'items' => [[
                'item_name' => 'Updated service',
                'description' => 'Updated',
                'quantity' => 1,
                'unit_price' => 1000,
            ]],
        ])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh()->notes)->toBe('Updated draft')
        ->and(ActivityLog::where('workspace_id', $workspace->id)->where('type', 'invoice.updated')->exists())->toBeFalse();
});

test('draft invoices can be soft deleted and restored', function (): void {
    [$user, $workspace, , , $invoice] = createInvoiceLifecycleFixture();

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->delete(route('invoices.destroy', [$workspace, $invoice]))
        ->assertRedirect(route('invoices.index', $workspace));

    expect($invoice->refresh()->trashed())->toBeTrue()
        ->and(Invoice::query()->whereKey($invoice->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->post(route('invoices.restore', [$workspace, $invoice->id]))
        ->assertRedirect(route('invoices.deleted', $workspace));

    expect($invoice->refresh()->trashed())->toBeFalse();
});

test('only draft invoices can be permanently deleted with their draft records', function (): void {
    [$user, $workspace, , , $invoice] = createInvoiceLifecycleFixture();
    $invoice->delete();

    $this->actingAs($user)
        ->delete(route('invoices.force-delete', [$workspace, $invoice->id]))
        ->assertRedirect(route('invoices.deleted', $workspace));

    expect(Invoice::withTrashed()->whereKey($invoice->id)->exists())->toBeFalse()
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

test('sent edits are audited and sent invoices cannot be deleted', function (): void {
    [$user, $workspace, $client, $currency, $invoice] = createInvoiceLifecycleFixture(Invoice::STATUS_SENT);

    $this->actingAs($user)
        ->from(route('invoices.edit', [$workspace, $invoice]))
        ->put(route('invoices.update', [$workspace, $invoice]), [
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'issue_date' => '2026-06-02',
            'due_date' => '2026-07-01',
            'status' => Invoice::STATUS_SENT,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'notes' => 'Sent edit',
            'items' => [['item_name' => 'Edited', 'description' => '', 'quantity' => 1, 'unit_price' => 1000]],
        ])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect(ActivityLog::where('workspace_id', $workspace->id)->where('type', 'invoice.updated')->count())->toBe(1);

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->delete(route('invoices.destroy', [$workspace, $invoice]))
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh()->trashed())->toBeFalse();
});

test('partially paid invoices cannot be reduced below payments and cannot be voided while paid', function (): void {
    [$user, $workspace, $client, $currency, $invoice] = createInvoiceLifecycleFixture(Invoice::STATUS_SENT);
    app(PaymentService::class)->createPayment($workspace, $invoice, [
        'amount' => 600,
        'payment_date' => '2026-06-10',
    ]);

    $this->actingAs($user)
        ->from(route('invoices.edit', [$workspace, $invoice]))
        ->put(route('invoices.update', [$workspace, $invoice]), [
            'invoice_number' => $invoice->invoice_number,
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-30',
            'status' => Invoice::STATUS_PARTIAL,
            'tax_amount' => 0,
            'discount_amount' => 500,
            'items' => [['item_name' => 'Reduced', 'description' => '', 'quantity' => 1, 'unit_price' => 500]],
        ])
        ->assertRedirect(route('invoices.edit', [$workspace, $invoice]))
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->post(route('invoices.void', [$workspace, $invoice]), ['void_reason' => 'Close unpaid invoice'])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]))
        ->assertSessionHas('error');

    expect($invoice->refresh()->status)->toBe(Invoice::STATUS_PARTIAL);
});

test('partially paid invoices can be voided after payments are cleared', function (): void {
    [$user, $workspace, , , $invoice] = createInvoiceLifecycleFixture(Invoice::STATUS_SENT);
    $payment = $invoice->payments()->create(['workspace_id' => $workspace->id, 'amount' => 600, 'payment_date' => '2026-06-10']);
    $invoice->forceFill(['status' => Invoice::STATUS_PARTIAL])->save();
    $payment->delete();

    $this->actingAs($user)
        ->post(route('invoices.void', [$workspace, $invoice]), ['void_reason' => 'Customer account closed'])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh()->status)->toBe(Invoice::STATUS_VOID)
        ->and($invoice->void_reason)->toBe('Customer account closed')
        ->and($invoice->voided_by)->toBe($user->id);
});

test('paid invoices allow non-financial edits but reject financial changes, delete, and workspace void', function (): void {
    [$user, $workspace, $client, $currency, $invoice] = createInvoiceLifecycleFixture(Invoice::STATUS_SENT);
    app(PaymentService::class)->createPayment($workspace, $invoice, ['amount' => 1000, 'payment_date' => '2026-06-10']);

    $this->actingAs($user)
        ->put(route('invoices.update', [$workspace, $invoice]), ['notes' => 'Paid note'])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh()->notes)->toBe('Paid note');

    $this->actingAs($user)
        ->from(route('invoices.edit', [$workspace, $invoice]))
        ->put(route('invoices.update', [$workspace, $invoice]), ['notes' => 'Bad', 'total_amount' => 999])
        ->assertRedirect(route('invoices.edit', [$workspace, $invoice]))
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->delete(route('invoices.destroy', [$workspace, $invoice]))
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    $this->actingAs($user)
        ->post(route('invoices.void', [$workspace, $invoice]), ['void_reason' => 'Not allowed'])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh()->status)->toBe(Invoice::STATUS_PAID);
});

test('overdue invoices can be edited with an audit and voided', function (): void {
    [$user, $workspace, $client, $currency, $invoice] = createInvoiceLifecycleFixture(Invoice::STATUS_OVERDUE);

    $payload = [
        'invoice_number' => $invoice->invoice_number,
        'client_id' => $client->id,
        'currency_id' => $currency->id,
        'issue_date' => '2026-06-01',
        'due_date' => '2026-06-29',
        'status' => Invoice::STATUS_OVERDUE,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'notes' => 'Overdue edit',
        'items' => [['item_name' => 'Edited overdue', 'description' => '', 'quantity' => 1, 'unit_price' => 1000]],
    ];

    $this->actingAs($user)->put(route('invoices.update', [$workspace, $invoice]), $payload)->assertRedirect();
    expect(ActivityLog::where('workspace_id', $workspace->id)->where('type', 'invoice.updated')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('invoices.void', [$workspace, $invoice]), ['void_reason' => 'No longer collectible'])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    expect($invoice->refresh()->status)->toBe(Invoice::STATUS_VOID);
});

test('void invoices are read only, cannot receive payments, and cannot be emailed or reminded', function (): void {
    Mail::fake();
    [$user, $workspace, , , $invoice] = createInvoiceLifecycleFixture(Invoice::STATUS_VOID);
    $invoice->forceFill(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => 'Already void'])->save();

    $this->actingAs($user)
        ->get(route('invoices.edit', [$workspace, $invoice]))
        ->assertRedirect();

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->delete(route('invoices.destroy', [$workspace, $invoice]))
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]));

    $this->actingAs($user)
        ->from(route('invoices.show', [$workspace, $invoice]))
        ->post(route('invoices.payments.store', [$workspace, $invoice]), ['amount' => 10, 'payment_date' => '2026-06-10'])
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]))
        ->assertSessionHasErrors('invoice');

    $this->actingAs($user)
        ->post(route('invoices.send', [$workspace, $invoice]))
        ->assertRedirect(route('invoices.show', [$workspace, $invoice]))
        ->assertSessionHas('error');

    expect($invoice->refresh()->status)->toBe(Invoice::STATUS_VOID)
        ->and(Mail::assertNothingSent());
});

test('void invoices are excluded from reminder processing', function (): void {
    [, $workspace, , , $invoice] = createInvoiceLifecycleFixture(Invoice::STATUS_VOID);
    $invoice->forceFill(['due_date' => '2026-06-10'])->save();
    ReminderSchedule::create([
        'workspace_id' => $workspace->id,
        'name' => 'Before due',
        'days_offset' => 1,
        'direction' => ReminderSchedule::DIRECTION_BEFORE_DUE,
        'is_active' => true,
    ]);

    $summary = app(ReminderService::class)->process(Carbon::parse('2026-06-09'));

    expect($summary['reminders_created'])->toBe(0)
        ->and($invoice->reminderLogs()->count())->toBe(0);
});

test('invoice lifecycle actions remain isolated between workspaces', function (): void {
    [$user, $workspace, , , $invoice] = createInvoiceLifecycleFixture();
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'owner_id' => $otherUser->id,
        'name' => 'Other Workspace',
        'slug' => 'other-lifecycle-workspace',
        'subdomain' => 'other-lifecycle-workspace',
        'invoice_prefix' => 'OTH',
        'is_active' => true,
    ]);
    $otherWorkspace->users()->attach($otherUser->id, ['role' => 'admin', 'is_active' => true]);

    $this->actingAs($otherUser)
        ->delete(route('invoices.destroy', [$otherWorkspace, $invoice]))
        ->assertNotFound();

    expect($invoice->refresh()->trashed())->toBeFalse();
});
