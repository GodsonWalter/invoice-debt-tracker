<?php

use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;

/**
 * @return array{0: User, 1: Workspace, 2: BusinessProfile, 3: string}
 */
function businessProfileFixture(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Business Profile Workspace',
        'slug' => 'business-profile-workspace-'.fake()->unique()->numerify('####'),
        'subdomain' => 'business-profile-'.fake()->unique()->numerify('####'),
        'invoice_prefix' => 'BPF',
        'is_active' => true,
    ]);
    $workspace->users()->attach($user->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);
    $businessProfile = BusinessProfile::create([
        'workspace_id' => $workspace->id,
        'business_name' => $workspace->name,
    ]);
    $url = 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route(
        'business-profile.update',
        $businessProfile,
        false,
    );

    return [$user, $workspace, $businessProfile, $url];
}

test('business profile descriptions are limited to 10 through 500 characters', function () {
    [$user, $workspace, $businessProfile, $url] = businessProfileFixture();
    $indexUrl = 'http://'.$workspace->subdomain.'.'.config('app.base_domain').route(
        'business-profile.index',
        [],
        false,
    );

    $this->actingAs($user)
        ->get($indexUrl)
        ->assertOk()
        ->assertSee('minlength="10"', false)
        ->assertSee('maxlength="500"', false);

    $this->actingAs($user)
        ->from($url)
        ->put($url, [
            '_token' => csrf_token(),
            'business_name' => $businessProfile->business_name,
            'business_description' => 'Too short',
        ])
        ->assertSessionHasErrors('business_description');

    $this->actingAs($user)
        ->from($url)
        ->put($url, [
            '_token' => csrf_token(),
            'business_name' => $businessProfile->business_name,
            'business_description' => str_repeat('a', 501),
        ])
        ->assertSessionHasErrors('business_description');

    $this->actingAs($user)
        ->put($url, [
            '_token' => csrf_token(),
            'business_name' => $businessProfile->business_name,
            'business_description' => str_repeat('a', 10),
        ])
        ->assertSessionHasNoErrors();

    expect($businessProfile->refresh()->business_description)->toHaveLength(10)
        ->and(Schema::getColumnType('business_profiles', 'business_description'))->toBe('text');
});
