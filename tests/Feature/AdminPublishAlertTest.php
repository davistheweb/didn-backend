<?php

use App\Models\Event;
use App\Models\Post;

it('alerts the admin inbox when a post is created published', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();

    $this->postJson('/api/v1/admin/posts', [
        'title' => 'Breaking Coverage',
        'status' => 'published',
    ])->assertCreated();

    expect($spy->sent)->toHaveCount(1);
    $sent = $spy->sent[0];
    expect($sent['to'])->toBe(['admin@didn.test'])
        ->and($sent['subject'])->toBe('New blog post published: Breaking Coverage')
        ->and($sent['from'])->toBe('info@didn.test')
        ->and($sent['html'])->toContain('Breaking Coverage')
        ->and($sent['html'])->toContain('https://www.didn.test/blog/breaking-coverage');
});

it('does not alert the admin inbox for draft posts', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();

    $this->postJson('/api/v1/admin/posts', [
        'title' => 'Just Drafting',
        'status' => 'draft',
    ])->assertCreated();

    expect($spy->sent)->toBe([]);
});

it('alerts the admin inbox only on the draft to published transition', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();
    $post = Post::factory()->draft()->create();

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['title' => 'Still Draft'])
        ->assertOk();
    expect($spy->sent)->toBe([]);

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['status' => 'published'])
        ->assertOk();

    expect($spy->sent)->toHaveCount(1);
    expect($spy->sent[0]['subject'])->toBe('New blog post published: '.$post->refresh()->title);
});

it('does not re-alert the admin inbox when editing a published post', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();
    $post = Post::factory()->published()->create();

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['title' => 'Touch Up'])
        ->assertOk();

    expect($spy->sent)->toBe([]);
});

it('alerts the admin inbox when an event is created published', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();

    $this->postJson('/api/v1/admin/events', [
        'title' => 'Annual Gala',
        'description' => 'A night of celebration.',
        'event_type' => 'conference',
        'start_date' => '2026-04-01 10:00:00',
        'is_published' => true,
    ])->assertCreated();

    expect($spy->sent)->toHaveCount(1);
    $sent = $spy->sent[0];
    expect($sent['to'])->toBe(['admin@didn.test'])
        ->and($sent['subject'])->toBe('New event published: Annual Gala')
        ->and($sent['from'])->toBe('info@didn.test')
        ->and($sent['html'])->toContain('Annual Gala')
        ->and($sent['html'])->toContain('A night of celebration.')
        ->and($sent['html'])->toContain('April 1, 2026')
        ->and($sent['html'])->toContain('https://www.didn.test/events/annual-gala');
});

it('does not alert the admin inbox for unpublished events', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();

    $this->postJson('/api/v1/admin/events', [
        'title' => 'Hidden Event',
        'description' => 'About this event.',
        'event_type' => 'webinar',
        'start_date' => '2026-04-01 10:00:00',
        'is_published' => false,
    ])->assertCreated();

    expect($spy->sent)->toBe([]);
});

it('alerts the admin inbox only when an event is published', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();
    $event = Event::factory()->unpublished()->create();

    $this->putJson("/api/v1/admin/events/{$event->id}", ['is_published' => true])
        ->assertOk();

    expect($spy->sent)->toHaveCount(1);
    expect($spy->sent[0]['subject'])->toBe('New event published: '.$event->refresh()->title);
});

it('does not re-alert the admin inbox when updating a published event', function () {
    actingAsAdmin();
    $spy = fakeResendEmailService();
    $event = Event::factory()->published()->create();

    $this->putJson("/api/v1/admin/events/{$event->id}", ['location' => 'Port Harcourt'])
        ->assertOk();

    expect($spy->sent)->toBe([]);
});
