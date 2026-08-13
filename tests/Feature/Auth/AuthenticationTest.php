<?php

use App\Models\PlatformSetting;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('Welcome back')
        ->assertSee('Sign in to your account')
        ->assertSee('Email address')
        ->assertSee('Forgot password?')
        ->assertSee('Create an account')
        ->assertSee('togglePassword')
        ->assertSee('data-theme-toggle', false)
        ->assertSee('idt.theme', false);
});

test('login screen uses the configured platform product name', function (): void {
    PlatformSetting::factory()->create([
        'product_name' => 'LedgerPro',
        'product_title' => 'Legacy Product Title',
    ]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('LedgerPro')
        ->assertDontSee('Legacy Product Title');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
