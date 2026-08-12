<?php

use App\Models\User;

test('guests can view the public homepage and its authentication links', function (): void {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('Create Invoices. Track Payments. Recover Debts Faster.')
        ->assertSee('Everything you need to manage invoices and debts')
        ->assertSee('Get started in four simple steps')
        ->assertSee('Customer stories')
        ->assertSee('Customer stories will appear here')
        ->assertSee('Frequently asked questions')
        ->assertSee('Revenue')
        ->assertSee('Outstanding debt')
        ->assertSee('Recent customer payments')
        ->assertSee(route('login', [], false), false)
        ->assertSee(route('register', [], false), false);
});

test('authenticated users can access the public homepage without workspace data', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Open Dashboard')
        ->assertSee(route('dashboard', [], false), false)
        ->assertDontSee('Sign In')
        ->assertDontSee('Get Started Free');
});
