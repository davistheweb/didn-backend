<?php

use App\Models\NewsletterSubscriber;

it('denies anonymous access to admin subscriber endpoints', function () {
    $this->getJson('/api/v1/admin/newsletter/subscribers')->assertStatus(401);
    $this->getJson('/api/v1/admin/newsletter/subscribers/1')->assertStatus(401);
    $this->deleteJson('/api/v1/admin/newsletter/subscribers/1')->assertStatus(401);
});

it('lists subscribers and filters by status and search', function () {
    actingAsAdmin();
    NewsletterSubscriber::factory()->create(['email' => 'a@example.com']);
    NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'b@example.com']);

    $this->getJson('/api/v1/admin/newsletter/subscribers')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $this->getJson('/api/v1/admin/newsletter/subscribers?status=unsubscribed')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.email', 'b@example.com');

    $this->getJson('/api/v1/admin/newsletter/subscribers?search=b@example.com')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.email', 'b@example.com');
});

it('shows a single subscriber', function () {
    actingAsAdmin();
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'a@example.com']);

    $this->getJson("/api/v1/admin/newsletter/subscribers/{$subscriber->id}")
        ->assertOk()
        ->assertJsonPath('data.email', 'a@example.com')
        ->assertJsonPath('data.status', 'subscribed');
});

it('marks a subscriber unsubscribed on delete without removing the record', function () {
    actingAsAdmin();
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'a@example.com']);

    $this->deleteJson("/api/v1/admin/newsletter/subscribers/{$subscriber->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Subscriber unsubscribed successfully.');

    expect($subscriber->fresh()->status)->toBe(NewsletterSubscriber::STATUS_UNSUBSCRIBED)
        ->and(NewsletterSubscriber::where('email', 'a@example.com')->count())->toBe(1);
});

it('exposes a stable subscriber resource shape without the token', function () {
    actingAsAdmin();
    NewsletterSubscriber::factory()->create(['email' => 'a@example.com']);

    $this->getJson('/api/v1/admin/newsletter/subscribers')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [[
                'id', 'email', 'status', 'subscribed_at', 'unsubscribed_at',
                'created_at',
            ]],
        ])
        ->assertJsonMissingPath('data.0.unsubscribe_token')
        ->assertJsonMissingPath('data.0.token');
});
