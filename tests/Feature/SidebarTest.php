<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
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
        ->assertDontSee('AI Query')
        ->assertDontSee('/workspace/', false);
});

test('sidebar shows workspace links for an active workspace', function () {
    [$user, $workspace] = createSidebarWorkspace('active-sidebar-workspace');

    $response = $this->actingAs($user)->get('http://active-sidebar-workspace.idt.test/dashboard');

    $response
        ->assertOk()
        ->assertSee('Sidebar Workspace')
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
        ->assertDontSee('/workspace/', false);

    $this->actingAs($user)
        ->get('http://isolated-sidebar-workspace.idt.test/workspace/'.$workspace->id.'/invoices')
        ->assertRedirect();
});
