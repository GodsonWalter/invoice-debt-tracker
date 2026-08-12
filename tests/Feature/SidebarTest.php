<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{0: User, 1: Workspace}
 */
function createSidebarWorkspace(string $subdomain, ?User $owner = null): array
{
    $owner ??= User::factory()->create();

    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => 'Sidebar Workspace',
        'slug' => $subdomain,
        'subdomain' => $subdomain,
        'invoice_prefix' => 'SIDE',
        'is_active' => true,
    ]);

    $workspace->users()->attach($owner->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    return [$owner, $workspace];
}

test('sidebar renders without an active workspace', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Workspaces')
        ->assertSee(route('home'), false)
        ->assertDontSee('AI Query')
        ->assertSee('/workspace/create', false);
});

test('sidebar shows workspace links for an active workspace', function () {
    [$user, $workspace] = createSidebarWorkspace('active-sidebar-workspace');

    $response = $this->actingAs($user)->get('http://active-sidebar-workspace.idt.test/dashboard');

    $response
        ->assertOk()
        ->assertSee('Sidebar Workspace')
        ->assertSee(route('home'), false)
        ->assertSee('AI Query')
        ->assertSee('Invoices')
        ->assertSee('Clients')
        ->assertSee('/workspace/'.$workspace->id.'/dashboard', false)
        ->assertSee('/workspace/'.$workspace->id.'/invoices', false)
        ->assertSee('/workspace/'.$workspace->id.'/clients', false)
        ->assertSee('/workspace/'.$workspace->id.'/user', false)
        ->assertSee('/workspace/'.$workspace->id.'/email-templates', false)
        ->assertSee('/workspace/'.$workspace->id.'/reminder-schedules', false);
});

test('sidebar hides workspace links for a user outside the active workspace', function () {
    $user = User::factory()->create();
    [, $workspace] = createSidebarWorkspace('isolated-sidebar-workspace');

    $response = $this->actingAs($user)->get('http://isolated-sidebar-workspace.idt.test/dashboard');

    $response
        ->assertOk()
        ->assertDontSee('AI Query')
        ->assertSee('/workspace/create', false);

    $this->actingAs($user)
        ->get('http://isolated-sidebar-workspace.idt.test/workspace/'.$workspace->id.'/invoices')
        ->assertRedirect();
});

test('sidebar highlights invoices on nested invoice routes', function (): void {
    [$user, $workspace] = createSidebarWorkspace('nested-sidebar-workspace');

    $currency = Currency::create([
        'code' => 'NWS',
        'symbol' => '$',
        'name' => 'Nested Workspace Currency',
        'is_active' => true,
    ]);
    $workspace->update(['currency_id' => $currency->id]);

    $client = Client::create([
        'workspace_id' => $workspace->id,
        'name' => 'Nested Route Client',
        'email' => 'nested-route@example.test',
    ]);
    $invoice = Invoice::create([
        'workspace_id' => $workspace->id,
        'client_id' => $client->id,
        'currency_id' => $currency->id,
        'invoice_number' => 'NWS-2026-0001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => Invoice::STATUS_DRAFT,
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 100,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'item_name' => 'Nested Route Item',
        'description' => 'Sidebar test item',
        'quantity' => 1,
        'unit_price' => 100,
        'total_price' => 100,
    ]);

    $invoiceIndexUrl = route('invoices.index', $workspace, false);
    $editUrl = 'http://'.$workspace->subdomain.'.idt.test'.route('invoices.edit', [$workspace, $invoice], false);

    $response = $this->actingAs($user)->get($editUrl);

    $response->assertOk();

    expect($response->getContent())
        ->toMatch('/href="'.preg_quote($invoiceIndexUrl, '/').'"[^>]*class="nav-link active"[^>]*aria-current="page"/');
});

test('sidebar expands reminder menu and highlights the active child route', function (): void {
    [$user, $workspace] = createSidebarWorkspace('reminder-sidebar-workspace');

    $url = 'http://'.$workspace->subdomain.'.idt.test'.route('reminders.sent', [], false);
    $response = $this->actingAs($user)->get($url);

    $response->assertOk();

    expect($response->getContent())
        ->toMatch('/<li class="nav-item has-dropdown open">\s*<a href="#" class="nav-link active"\s+aria-expanded="true"/')
        ->toMatch('/href="'.preg_quote($url, '/').'" class="active"\s+aria-current="page"/');
});

test('sidebar keeps active-link scrolling inside the sidebar', function (): void {
    [$user, $workspace] = createSidebarWorkspace('scroll-sidebar-workspace');

    $url = 'http://'.$workspace->subdomain.'.idt.test'.route('invoices.index', $workspace, false);
    $response = $this->actingAs($user)->get($url);

    $response
        ->assertOk()
        ->assertSee('function revealActiveSidebarLink()', false)
        ->assertSee('sidebar.scrollTop', false)
        ->assertDontSee('activeLink.scrollIntoView', false);
});
