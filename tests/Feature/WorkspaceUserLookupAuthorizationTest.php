<?php

use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{0: User, 1: Workspace}
 */
function createWorkspaceLookupFixture(string $key = 'lookup'): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::create([
        'owner_id' => $owner->id,
        'name' => ucfirst($key).' workspace',
        'slug' => $key.'-'.fake()->unique()->numerify('####'),
        'subdomain' => $key.'-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => strtoupper(substr($key, 0, 3)),
        'is_active' => true,
    ]);
    $workspace->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

    return [$owner, $workspace];
}

function workspaceLookupUrl(Workspace $workspace): string
{
    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route('workspace.users.lookup', $workspace, false);
}

test('workspace managers can use a limited workspace-user lookup', function (): void {
    [$owner, $workspace] = createWorkspaceLookupFixture();
    $candidate = User::factory()->create([
        'name' => 'Lookup Candidate',
        'email' => 'lookup-candidate@example.test',
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($owner)->get(workspaceLookupUrl($workspace).'?email='.urlencode($candidate->email));

    $response->assertOk()
        ->assertJson([
            'exists' => true,
            'user' => [
                'name' => 'Lookup Candidate',
                'email' => 'lookup-candidate@example.test',
                'verified' => false,
            ],
        ])
        ->assertJsonMissingPath('user.id')
        ->assertJsonMissingPath('user.role');
});

test('workspace-user lookup rejects members without management permission', function (): void {
    [, $workspace] = createWorkspaceLookupFixture('member-lookup');
    $member = User::factory()->create();
    $workspace->users()->attach($member->id, ['role' => 'member', 'is_active' => true]);

    $this->actingAs($member)
        ->get(workspaceLookupUrl($workspace).'?email=someone@example.test')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');
});

test('workspace-user lookup rejects inactive memberships and mismatched active workspaces', function (): void {
    [$owner, $workspace] = createWorkspaceLookupFixture('inactive-lookup');
    $workspace->users()->updateExistingPivot($owner->id, ['is_active' => false]);

    $this->actingAs($owner)
        ->get(workspaceLookupUrl($workspace).'?email=someone@example.test')
        ->assertRedirect(route('dashboard'));

    [$owner, $otherWorkspace] = createWorkspaceLookupFixture('other-lookup');
    $workspace->users()->attach($owner->id, ['role' => 'admin', 'is_active' => true]);

    $this->actingAs($owner)
        ->get('http://'.$workspace->subdomain.'.'.config('app.base_domain').route('workspace.users.lookup', $otherWorkspace, false).'?email=someone@example.test')
        ->assertRedirect(route('workspace.index'))
        ->assertSessionHas('error', 'Please switch to this workspace before accessing it.');
});

test('workspace-user lookup validates bounded input', function (): void {
    [$owner, $workspace] = createWorkspaceLookupFixture('bounded-lookup');

    $this->actingAs($owner)
        ->get(workspaceLookupUrl($workspace).'?email='.str_repeat('a', 256))
        ->assertSessionHasErrors('email');
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
