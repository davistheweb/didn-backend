<?php

use App\Models\Event;

it('returns a consistent success envelope', function () {
    actingAsAdmin();

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data']);
});

it('returns a consistent list envelope with pagination metadata', function () {
    actingAsAdmin();
    Event::factory()->published()->count(3)->create();

    $response = $this->getJson('/api/v1/events?per_page=2')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'current_page', 'last_page', 'per_page', 'total', 'from', 'to',
                'path', 'first_page_url', 'last_page_url', 'next_page_url', 'prev_page_url',
            ],
        ])
        ->assertJsonPath('success', true);

    expect($response->json('data'))->toHaveCount(2);
});

it('returns a validation error envelope', function () {
    actingAsAdmin();

    $this->postJson('/api/v1/admin/events', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['message', 'errors']);
});

it('returns an unauthorized envelope', function () {
    $this->getJson('/api/v1/admin/posts')->assertStatus(401)->assertJsonPath('success', false);
});

it('returns a not found envelope for public resources', function () {
    $this->getJson('/api/v1/posts/nope')->assertStatus(404)->assertJsonPath('success', false);
    $this->getJson('/api/v1/events/nope')->assertStatus(404)->assertJsonPath('success', false);
});

it('caps per_page', function () {
    actingAsAdmin();
    Event::factory()->count(10)->create();

    $this->getJson('/api/v1/events?per_page=999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('validates the allowed event types', function () {
    actingAsAdmin();

    foreach (['conference', 'campaign', 'training', 'webinar'] as $type) {
        $this->postJson('/api/v1/admin/events', [
            'title' => "Event {$type}",
            'description' => "A {$type} description.",
            'event_type' => $type,
            'start_date' => now()->addDays(2),
            'content' => "<p>{$type} body</p>",
        ])->assertCreated();
    }
});
