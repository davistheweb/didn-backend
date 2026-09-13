<?php

use App\Models\Event;
use App\Models\Media;

it('denies anonymous access to admin event endpoints', function () {
    $this->getJson('/api/v1/admin/events')->assertStatus(401);
    $this->postJson('/api/v1/admin/events')->assertStatus(401);
});

it('creates an event and auto-generates a unique slug', function () {
    actingAsAdmin();
    $media = Media::create([
        'disk' => 'public',
        'path' => 'backend/uploads/2026/09/cover.jpg',
        'original_name' => 'cover.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 100,
    ]);

    $response = $this->postJson('/api/v1/admin/events', [
        'title' => 'Annual Policy Conference',
        'event_type' => 'conference',
        'location' => 'Abuja',
        'start_date' => '2026-10-01T09:00:00Z',
        'end_date' => '2026-10-02T18:00:00Z',
        'description' => 'A description.',
        'content' => '<p>Body</p>',
        'featured_image_id' => $media->id,
        'is_published' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.slug', 'annual-policy-conference')
        ->assertJsonPath('data.type', 'conference')
        ->assertJsonPath('data.status', 'upcoming')
        ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'start_date', 'end_date', 'image']]);

    expect($response->json('data.image'))->toContain('/storage/backend/uploads/2026/09/cover.jpg');

    $this->assertDatabaseHas('events', ['slug' => 'annual-policy-conference']);
});

it('allows creating an event without content and reports it as empty', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/v1/admin/events', [
        'title' => 'Tentative Event',
        'description' => 'Details coming soon.',
        'event_type' => 'training',
        'start_date' => now()->addWeek(),
    ])->assertCreated();

    expect($response->json('data.content'))->toBeNull()
        ->and($response->json('data.has_content'))->toBeFalse();

    $id = $response->json('data.id');

    $this->getJson("/api/v1/admin/events/{$id}")
        ->assertOk()
        ->assertJsonPath('data.content', null)
        ->assertJsonPath('data.has_content', false);
});

it('sanitizes dangerous content on create', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/v1/admin/events', [
        'title' => 'Webinar',
        'description' => 'A webinar description.',
        'event_type' => 'webinar',
        'start_date' => now()->addDays(3),
        'content' => '<p>Safe</p><script>alert(1)</script><iframe src="x"></iframe><img src="data:text/html,x">',
    ])->assertCreated();

    $content = $response->json('data.content');

    expect($content)->toContain('<p>Safe</p>')
        ->not->toContain('script')
        ->not->toContain('iframe')
        ->not->toContain('data:text/html');
});

it('validates the event payload', function () {
    actingAsAdmin();

    $this->postJson('/api/v1/admin/events', [
        'title' => 'Bad',
        'event_type' => 'hackathon',
        'start_date' => 'not-a-date',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['event_type', 'start_date']]);
});

it('updates an event and keeps slugs unique', function () {
    actingAsAdmin();
    Event::factory()->create(['slug' => 'shared-slug', 'title' => 'First']);

    $event = Event::factory()->create(['slug' => 'original-slug', 'title' => 'Original']);

    $this->putJson("/api/v1/admin/events/{$event->id}", [
        'title' => 'Renamed Event',
        'slug' => 'shared-slug',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Renamed Event')
        ->assertJsonPath('data.slug', 'shared-slug-2');

    $this->assertDatabaseHas('events', ['id' => $event->id, 'slug' => 'shared-slug-2']);
});

it('lets admins clear content on update', function () {
    actingAsAdmin();
    $event = Event::factory()->create(['content' => '<p>Old body.</p>']);

    $this->putJson("/api/v1/admin/events/{$event->id}", ['content' => null])
        ->assertOk()
        ->assertJsonPath('data.content', null)
        ->assertJsonPath('data.has_content', false);
});

it('deletes an event', function () {
    actingAsAdmin();
    $event = Event::factory()->create();

    $this->deleteJson("/api/v1/admin/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

it('lists all events for admins including unpublished ones', function () {
    actingAsAdmin();
    Event::factory()->published()->count(2)->create();
    Event::factory()->unpublished()->create();

    $this->getJson('/api/v1/admin/events')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.status', 'upcoming');
});

it('only exposes published events publicly', function () {
    actingAsAdmin();
    Event::factory()->published()->create(['title' => 'Visible Event']);
    Event::factory()->unpublished()->create(['title' => 'Hidden Event']);

    $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Visible Event');
});

it('returns an event by public slug only when published', function () {
    actingAsAdmin();
    $event = Event::factory()->published()->create(['slug' => 'public-event']);
    $draft = Event::factory()->unpublished()->create(['slug' => 'draft-event']);

    $this->getJson('/api/v1/events/public-event')->assertOk()->assertJsonPath('data.slug', 'public-event');
    $this->getJson('/api/v1/events/draft-event')->assertNotFound()->assertJsonPath('success', false);
    $this->getJson('/api/v1/events/does-not-exist')->assertNotFound()->assertJsonPath('success', false);
});

it('filters public events by type', function () {
    actingAsAdmin();
    Event::factory()->published()->create(['title' => 'Training One', 'event_type' => 'training']);
    Event::factory()->published()->create(['title' => 'Conference One', 'event_type' => 'conference']);

    $this->getJson('/api/v1/events?type=training')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.type', 'training');
});

it('filters upcoming and past events from their dates', function () {
    actingAsAdmin();
    $upcoming = Event::factory()->published()->upcoming()->create(['title' => 'Coming Soon']);
    $past = Event::factory()->published()->past()->create(['title' => 'Long Gone']);

    $this->getJson('/api/v1/events?status=upcoming')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.slug', $upcoming->slug);

    $this->getJson('/api/v1/events?status=past')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.slug', $past->slug);
});

it('derives event status from dates', function () {
    $past = Event::factory()->create(['start_date' => now()->subDays(5), 'end_date' => now()->subDays(3)]);
    $upcoming = Event::factory()->create(['start_date' => now()->addDays(5), 'end_date' => null]);

    expect($past->status)->toBe('past');
    expect($upcoming->status)->toBe('upcoming');
});

it('paginates events with consistent metadata', function () {
    actingAsAdmin();
    Event::factory()->published()->count(5)->create();

    $this->getJson('/api/v1/events?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonCount(2, 'data');
});
