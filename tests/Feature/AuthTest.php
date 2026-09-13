<?php

use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials and returns a bearer token', function () {
    $admin = makeAdmin(['email' => 'boss@didn.org']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'boss@didn.org',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email']],
        ]);

    expect($admin->tokens()->count())->toBe(1);
});

it('rejects invalid credentials', function () {
    makeAdmin(['email' => 'boss@didn.org']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'boss@didn.org',
        'password' => 'wrong-password',
    ])
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The provided credentials are incorrect.');
});

it('requires email and password', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['email', 'password']]);
});

it('returns the authenticated admin from me', function () {
    $admin = actingAsAdmin(['name' => 'DIDN Admin']);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.name', 'DIDN Admin')
        ->assertJsonMissing(['data' => ['password' => null]]);
});

it('requires authentication for me', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('logs out the current admin', function () {
    actingAsAdmin();

    $this->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('never leaks password hashes', function () {
    $admin = actingAsAdmin();

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonMissingPath('data.password');

    expect((string) $admin->password)->not->toBe('password')
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});
