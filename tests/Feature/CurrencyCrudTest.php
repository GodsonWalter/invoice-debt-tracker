<?php

use App\Http\Middleware\EnsureWorkspaceIsActive;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Currency;
use App\Models\User;

test('system owner can create update and delete currencies', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $this->actingAs($owner)
        ->post(route('currencies.store'), [
            'code' => 'aud',
            'symbol' => '$',
            'name' => 'Australian Dollar',
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $currency = Currency::where('code', 'AUD')->first();

    expect($currency)
        ->not->toBeNull()
        ->is_active->toBeTrue();

    $this->actingAs($owner)
        ->put(route('currencies.update', $currency), [
            'symbol' => 'A$',
            'name' => 'Australian Dollar',
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('currencies.index'));

    expect($currency->refresh())
        ->symbol->toBe('A$')
        ->is_active->toBeTrue();

    $currencyId = $currency->id;

    $this->actingAs($owner)
        ->delete(route('currencies.destroy', $currency))
        ->assertRedirect();

    expect(Currency::find($currencyId))->toBeNull();
    expect(Currency::withTrashed()->find($currencyId))
        ->not->toBeNull()
        ->deleted_at->not->toBeNull();

    $this->actingAs($owner)
        ->get(route('currencies.edit', $currencyId))
        ->assertNotFound();
});

test('non system managers cannot manage currencies', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $user = User::factory()->create([
        'role' => 'user',
    ]);

    $this->actingAs($user)
        ->get(route('currencies.index'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');
});

test('currency index can filter by status and sort safely', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $activeAlpha = Currency::create([
        'code' => 'ALP',
        'symbol' => 'A$',
        'name' => 'Alpha Dollar',
        'is_active' => true,
    ]);
    $inactive = Currency::create([
        'code' => 'BET',
        'symbol' => 'B$',
        'name' => 'Beta Dollar',
        'is_active' => false,
    ]);
    $activeGamma = Currency::create([
        'code' => 'GAM',
        'symbol' => 'G$',
        'name' => 'Gamma Dollar',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('currencies.index', [
        'search' => 'Dollar',
        'status' => 'active',
        'sort' => 'name',
        'direction' => 'desc',
    ]));

    $response->assertOk()
        ->assertViewHas('filters', [
            'search' => 'Dollar',
            'status' => 'active',
            'sort' => 'name',
            'direction' => 'desc',
        ]);

    expect($response->viewData('currencies')->pluck('id')->all())
        ->toBe([$activeGamma->id, $activeAlpha->id]);

    expect($response->viewData('currencies')->contains($inactive))->toBeFalse();
    $response->assertSee(e(route('currencies.index', [
        'search' => 'Dollar',
        'status' => 'active',
        'sort' => 'name',
        'direction' => 'asc',
    ])), false);
});

test('currency managers can restore a soft deleted currency', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $currency = Currency::create([
        'code' => 'REC',
        'symbol' => 'R$',
        'name' => 'Recovery Dollar',
        'is_active' => true,
    ]);
    $currency->delete();

    $this->actingAs($admin)
        ->get(route('currencies.index', ['status' => 'deleted']))
        ->assertOk()
        ->assertSee('Recovery Dollar')
        ->assertSee(route('currencies.restore', $currency->id), false);

    $this->actingAs($admin)
        ->patch(route('currencies.restore', $currency->id))
        ->assertRedirect(route('currencies.index', ['status' => 'deleted']))
        ->assertSessionHas('success', 'Currency restored successfully.');

    expect(Currency::find($currency->id))
        ->not->toBeNull()
        ->deleted_at->toBeNull();
});

test('non system managers cannot restore soft deleted currencies', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $user = User::factory()->create([
        'role' => 'user',
    ]);
    $currency = Currency::create([
        'code' => 'NRS',
        'symbol' => 'N$',
        'name' => 'No Restore Dollar',
        'is_active' => true,
    ]);
    $currency->delete();

    $this->actingAs($user)
        ->patch(route('currencies.restore', $currency->id))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('error');

    expect(Currency::withTrashed()->find($currency->id)?->deleted_at)->not->toBeNull();
});

test('currency code must be unique uppercase iso style code', function () {
    $this->withoutMiddleware([EnsureWorkspaceIsActive::class, ResolveWorkspace::class]);

    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    Currency::create([
        'code' => 'USD',
        'symbol' => '$',
        'name' => 'US Dollar',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('currencies.create'))
        ->post(route('currencies.store'), [
            'code' => 'usd',
            'symbol' => '$',
            'name' => 'Duplicate Dollar',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('code')
        ->assertRedirect(route('currencies.create'));
});
