<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{0: User, 1: Workspace, 2: Workspace}
 */
function createWorkspaceIsolationFixture(): array
{
    $user = User::factory()->create();
    $first = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'First Isolation Workspace',
        'slug' => 'first-isolation-'.fake()->unique()->numerify('####'),
        'subdomain' => 'first-isolation-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => 'FIS',
        'is_active' => true,
    ]);
    $second = Workspace::create([
        'owner_id' => User::factory()->create()->id,
        'name' => 'Second Isolation Workspace',
        'slug' => 'second-isolation-'.fake()->unique()->numerify('####'),
        'subdomain' => 'second-isolation-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => 'SIS',
        'is_active' => true,
    ]);
    $first->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
    $second->users()->attach($user->id, ['role' => 'admin', 'is_active' => true]);

    return [$user, $first, $second];
}

function workspaceIsolationUrl(Workspace $hostWorkspace, string $routeName, Workspace $requestedWorkspace, array $parameters = []): string
{
    return 'http://'.$hostWorkspace->subdomain.'.'.config('app.base_domain').route($routeName, [$requestedWorkspace, ...$parameters], false);
}

test('workspace resource routes require the requested workspace to be active', function (): void {
    [$user, $first, $second] = createWorkspaceIsolationFixture();
    $client = Client::create([
        'workspace_id' => $second->id,
        'name' => 'Second Client',
        'email' => 'second-client@example.test',
    ]);

    $invoice = Invoice::create([
        'workspace_id' => $second->id,
        'client_id' => $client->id,
        'invoice_number' => 'SIS-00001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 100,
    ]);

    $this->actingAs($user)
        ->get(workspaceIsolationUrl($first, 'clients.index', $second))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');

    $this->actingAs($user)
        ->get(workspaceIsolationUrl($first, 'invoices.show', $second, [$invoice]))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');

    $this->actingAs($user)
        ->get('http://'.config('app.base_domain').route('clients.index', $second, false))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to a workspace before accessing it.');
});

test('all workspace resource groups reject route workspaces that are not active', function (string $routeName): void {
    [$user, $first, $second] = createWorkspaceIsolationFixture();

    $this->actingAs($user)
        ->get(workspaceIsolationUrl($first, $routeName, $second))
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');
})->with([
    'clients' => 'clients.index',
    'invoices' => 'invoices.index',
    'email templates' => 'email-templates.index',
    'reminder schedules' => 'reminder-schedules.index',
    'workspace users' => 'workspace.users.index',
]);

test('inactive memberships cannot access workspace resources or switch', function (): void {
    [$user, $first, $second] = createWorkspaceIsolationFixture();
    $second->users()->updateExistingPivot($user->id, ['is_active' => false]);

    $this->actingAs($user)
        ->get(workspaceIsolationUrl($second, 'clients.index', $second))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to access this workspace.');

    $this->actingAs($user)
        ->get('http://'.$second->subdomain.'.'.config('app.base_domain').'/switch')
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'You are not authorized to switch to this workspace.');
});

test('inactive memberships cannot generate workspace reports or exports', function (): void {
    [$user, , $workspace] = createWorkspaceIsolationFixture();
    $workspace->users()->updateExistingPivot($user->id, ['is_active' => false]);
    $host = 'http://'.$workspace->subdomain.'.'.config('app.base_domain');

    $this->actingAs($user)
        ->get($host.route('reports.index', ['report' => 'revenue'], false))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to manage this workspace.');

    $this->actingAs($user)
        ->post($host.route('reports.export.queue', [
            'report' => 'revenue',
            'format' => 'csv',
        ], false), [
            'period' => 'this_month',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error', 'You are not authorized to manage this workspace.');
});

test('switching to the requested workspace allows its authorized resources', function (): void {
    [$user, , $second] = createWorkspaceIsolationFixture();

    $this->actingAs($user)
        ->get('http://'.$second->subdomain.'.'.config('app.base_domain').'/switch')
        ->assertRedirect('http://'.$second->subdomain.'.'.config('app.base_domain').'/dashboard');

    $this->actingAs($user)
        ->get(workspaceIsolationUrl($second, 'clients.index', $second))
        ->assertOk();
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
