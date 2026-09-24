<?php

use App\Jobs\SendBlogNewsletterJob;
use App\Jobs\SendEventNewsletterJob;
use App\Models\Event;
use App\Models\Post;
use Illuminate\Support\Facades\Queue;

it('dispatches the blog newsletter job when a post is created published', function () {
    actingAsAdmin();
    Queue::fake([SendBlogNewsletterJob::class]);

    $this->postJson('/api/v1/admin/posts', [
        'title' => 'Published Right Away',
        'status' => 'published',
    ])->assertCreated();

    Queue::assertPushed(SendBlogNewsletterJob::class);
});

it('does not dispatch the blog newsletter job for drafts', function () {
    actingAsAdmin();
    Queue::fake([SendBlogNewsletterJob::class]);

    $this->postJson('/api/v1/admin/posts', [
        'title' => 'Draft Only',
        'status' => 'draft',
    ])->assertCreated();

    Queue::assertNotPushed(SendBlogNewsletterJob::class);
});

it('dispatches the blog newsletter job only on the draft to published transition', function () {
    actingAsAdmin();
    Queue::fake([SendBlogNewsletterJob::class]);
    $post = Post::factory()->draft()->create();

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['title' => 'Still Draft'])
        ->assertOk();
    Queue::assertNotPushed(SendBlogNewsletterJob::class);

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['status' => 'published'])
        ->assertOk();
    Queue::assertPushed(SendBlogNewsletterJob::class);
});

it('does not redispatch the blog newsletter job when editing a published post', function () {
    actingAsAdmin();
    Queue::fake([SendBlogNewsletterJob::class]);
    $post = Post::factory()->published()->create();

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['title' => 'Touch Up'])
        ->assertOk();

    Queue::assertNotPushed(SendBlogNewsletterJob::class);
});

it('dispatches the event newsletter job when an event is created published', function () {
    actingAsAdmin();
    Queue::fake([SendEventNewsletterJob::class]);

    $this->postJson('/api/v1/admin/events', [
        'title' => 'Launch Event',
        'description' => 'About this event.',
        'event_type' => 'webinar',
        'start_date' => '2026-04-01 10:00:00',
        'is_published' => true,
    ])->assertCreated();

    Queue::assertPushed(SendEventNewsletterJob::class);
});

it('does not dispatch the event newsletter job for unpublished events', function () {
    actingAsAdmin();
    Queue::fake([SendEventNewsletterJob::class]);

    $this->postJson('/api/v1/admin/events', [
        'title' => 'Hidden Event',
        'description' => 'About this event.',
        'event_type' => 'webinar',
        'start_date' => '2026-04-01 10:00:00',
        'is_published' => false,
    ])->assertCreated();

    Queue::assertNotPushed(SendEventNewsletterJob::class);
});

it('dispatches the event newsletter job only when is_published flips to true', function () {
    actingAsAdmin();
    Queue::fake([SendEventNewsletterJob::class]);
    $event = Event::factory()->unpublished()->create();

    $this->putJson("/api/v1/admin/events/{$event->id}", ['is_published' => true])
        ->assertOk();

    Queue::assertPushed(SendEventNewsletterJob::class);
});

it('does not redispatch the event newsletter job when updating a published event', function () {
    actingAsAdmin();
    Queue::fake([SendEventNewsletterJob::class]);
    $event = Event::factory()->published()->create();

    $this->putJson("/api/v1/admin/events/{$event->id}", ['location' => 'Port Harcourt'])
        ->assertOk();

    Queue::assertNotPushed(SendEventNewsletterJob::class);
});
