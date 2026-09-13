<?php

use Illuminate\Support\Facades\Hash;

it('shows the profile of the authenticated admin', function () {
    $admin = actingAsAdmin(['name' => 'Amina', 'job_title' => 'Programme Lead']);

    $this->getJson('/api/v1/admin/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.name', 'Amina')
        ->assertJsonPath('data.job_title', 'Programme Lead');
});

it('updates the profile', function () {
    $admin = actingAsAdmin();

    $this->putJson('/api/v1/admin/profile', [
        'name' => 'New Name',
        'email' => 'new@didn.org',
        'job_title' => 'Director',
        'phone' => '+2348000000000',
        'bio' => 'About me.',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.job_title', 'Director');

    expect($admin->fresh()->email)->toBe('new@didn.org');
});

it('validates unique email on profile update', function () {
    makeAdmin(['email' => 'taken@didn.org']);
    $admin = actingAsAdmin(['email' => 'me@didn.org']);

    $this->putJson('/api/v1/admin/profile', [
        'name' => 'Me',
        'email' => 'taken@didn.org',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['email']]);
});

it('changes the password when the current password is correct', function () {
    $admin = actingAsAdmin();

    $this->putJson('/api/v1/admin/password', [
        'current_password' => 'password',
        'new_password' => 'NewPass123!',
        'new_password_confirmation' => 'NewPass123!',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Hash::check('NewPass123!', $admin->fresh()->password))->toBeTrue();
});

it('rejects a password change with an incorrect current password', function () {
    actingAsAdmin();

    $this->putJson('/api/v1/admin/password', [
        'current_password' => 'not-the-password',
        'new_password' => 'NewPass123!',
        'new_password_confirmation' => 'NewPass123!',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['current_password']]);
});

it('validates new password confirmation', function () {
    actingAsAdmin();

    $this->putJson('/api/v1/admin/password', [
        'current_password' => 'password',
        'new_password' => 'NewPass123!',
        'new_password_confirmation' => 'Mismatch123!',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['new_password']]);
});

it('requires authentication for profile endpoints', function () {
    $this->getJson('/api/v1/admin/profile')->assertStatus(401);
    $this->putJson('/api/v1/admin/password')->assertStatus(401);
});
