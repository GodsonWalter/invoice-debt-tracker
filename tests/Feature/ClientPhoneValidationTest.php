<?php

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;

/**
 * @return array{0: User, 1: Workspace}
 */
function clientPhoneFixture(): array
{
    $user = User::factory()->create(['role' => 'owner']);
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'subdomain' => 'client-phone-'.fake()->unique()->numerify('####'),
    ]);
    $workspace->users()->attach($user->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);

    return [$user, $workspace];
}

function clientPhoneUrl(Workspace $workspace, string $routeName, ?Client $client = null): string
{
    $parameters = $client ? [$workspace, $client] : $workspace;

    return 'http://'.$workspace->subdomain.'.'.config('app.base_domain')
        .route($routeName, $parameters, false);
}

test('client creation normalizes formatted phone numbers to E.164', function (): void {
    [$user, $workspace] = clientPhoneFixture();

    $this->actingAs($user)
        ->post(clientPhoneUrl($workspace, 'clients.store'), [
            'name' => 'WhatsApp Client',
            'email' => 'whatsapp-client@example.test',
            'phone' => '+234 (800) 000-0000',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Client::query()->firstOrFail()->phone)->toBe('+2348000000000');
});

test('client creation rejects phone numbers without international country code', function (): void {
    [$user, $workspace] = clientPhoneFixture();

    $this->actingAs($user)
        ->post(clientPhoneUrl($workspace, 'clients.store'), [
            'name' => 'Invalid WhatsApp Client',
            'email' => 'invalid-whatsapp-client@example.test',
            'phone' => '08000000000',
        ])
        ->assertSessionHasErrors('phone');

    expect(Client::query()->count())->toBe(0);
});

test('client updates normalize formatted phone numbers and reject local numbers', function (): void {
    [$user, $workspace] = clientPhoneFixture();
    $client = $workspace->clients()->create([
        'name' => 'Existing Client',
        'email' => 'existing-client@example.test',
        'phone' => '+2348000000000',
    ]);

    $this->actingAs($user)
        ->put(clientPhoneUrl($workspace, 'clients.update', $client), [
            'name' => 'Existing Client',
            'email' => 'existing-client@example.test',
            'phone' => '+234 801 111 2222',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($client->refresh()->phone)->toBe('+2348011112222');

    $this->actingAs($user)
        ->put(clientPhoneUrl($workspace, 'clients.update', $client), [
            'name' => 'Existing Client',
            'email' => 'existing-client@example.test',
            'phone' => '08011112222',
        ])
        ->assertSessionHasErrors('phone');

    expect($client->refresh()->phone)->toBe('+2348011112222');
});
