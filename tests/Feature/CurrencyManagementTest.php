<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Client;
use App\Models\Currency;
use App\Models\User;
use App\Models\Workspace;

test('new workspaces inherit the authenticated user default currency', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $ngn = Currency::create([
        'code' => 'NGN',
        'symbol' => "\u{20A6}",
        'name' => 'Nigerian Naira',
        'is_active' => true,
    ]);

    Currency::create([
        'code' => 'USD',
        'symbol' => '$',
        'name' => 'US Dollar',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'default_currency_id' => $ngn->id,
    ]);

    $this->actingAs($user)
        ->post(route('workspace.store'), [
            'name' => 'Currency Workspace',
            'slug' => 'currency-workspace',
            'subdomain' => 'currency',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('workspace.index'));

    expect(Workspace::where('slug', 'currency-workspace')->first())
        ->currency_id->toBe($ngn->id);
});

test('invoice currency can be selected from active database currencies', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $usd = Currency::create([
        'code' => 'USD',
        'symbol' => '$',
        'name' => 'US Dollar',
        'is_active' => true,
    ]);

    $eur = Currency::create([
        'code' => 'EUR',
        'symbol' => "\u{20AC}",
        'name' => 'Euro',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'default_currency_id' => $usd->id,
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Invoice Currency',
        'slug' => 'invoice-currency',
        'subdomain' => 'invoice-currency',
        'invoice_prefix' => 'INV',
        'currency_id' => $usd->id,
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Currency Client',
    ]);

    $this->actingAs($user)
        ->post(route('invoices.store', $workspace), [
            'client_id' => $client->id,
            'currency_id' => $eur->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'tax_amount' => 0,
            'discount_amount' => 0,
            'items' => [
                [
                    'item_name' => 'Implementation',
                    'description' => 'Setup work',
                    'quantity' => 1,
                    'unit_price' => 250,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($workspace->invoices()->first())
        ->currency_id->toBe($eur->id)
        ->currency_symbol->toBe("\u{20AC}");
});
