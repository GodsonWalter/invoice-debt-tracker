<?php

use App\Http\Middleware\EnsureRouteWorkspaceMatchesActiveWorkspace;
use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\BusinessProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;

test('invoice show page displays the workspace business profile branding', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);

    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Main Workspace',
        'slug' => 'main-workspace',
        'subdomain' => 'main-workspace',
        'invoice_prefix' => 'INV',
        'is_active' => true,
    ]);

    $workspace->users()->attach($user->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    BusinessProfile::create([
        'workspace_id' => $workspace->id,
        'business_name' => 'Bright Ledger Studio',
        'email' => 'billing@brightledger.test',
        'phone' => '+234 800 555 0100',
        'address' => '14 Marina Road',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'country' => 'Nigeria',
        'tax_id' => 'TIN-123456',
    ]);

    $otherWorkspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Other Workspace',
        'slug' => 'other-workspace',
        'subdomain' => 'other-workspace',
        'invoice_prefix' => 'OTH',
        'is_active' => true,
    ]);

    BusinessProfile::create([
        'workspace_id' => $otherWorkspace->id,
        'business_name' => 'Hidden Tenant Ltd',
    ]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Acme Client',
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-0001',
        'issue_date' => '2026-05-29',
        'due_date' => '2026-06-05',
        'status' => 'sent',
        'subtotal' => 1000,
        'tax_amount' => 75,
        'discount_amount' => 25,
        'total_amount' => 1050,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.show', [$workspace, $invoice]))
        ->assertOk()
        ->assertSee('Bright Ledger Studio')
        ->assertSee('14 Marina Road, Lagos, Lagos, Nigeria')
        ->assertSee('billing@brightledger.test')
        ->assertSee('+234 800 555 0100')
        ->assertSee('Tax ID: TIN-123456')
        ->assertSee('INV-2026-0001')
        ->assertDontSee('Hidden Tenant Ltd');
});

test('invoice show page renders when the workspace has no business profile', function () {
    $this->withoutMiddleware([
        EnsureWorkspaceIsActive::class,
        EnsureRouteWorkspaceMatchesActiveWorkspace::class,
        ResolveWorkspace::class,
    ]);

    $user = User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Profile Pending Workspace',
        'slug' => 'profile-pending-workspace',
        'subdomain' => 'profile-pending-workspace',
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
        'invoice_number' => 'INV-2026-0002',
        'issue_date' => '2026-05-29',
        'due_date' => '2026-06-05',
        'status' => 'draft',
        'subtotal' => 500,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 500,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.show', [$workspace, $invoice]))
        ->assertOk()
        ->assertSee('Profile Pending Workspace')
        ->assertSee('business-logo-placeholder.svg');
});
