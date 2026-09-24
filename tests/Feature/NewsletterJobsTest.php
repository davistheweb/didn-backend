<?php

use App\Jobs\SendBlogNewsletterJob;
use App\Jobs\SendEventNewsletterJob;
use App\Models\Event;
use App\Models\NewsletterSubscriber;
use App\Models\Post;

it('sends the blog newsletter to every active subscriber with an unsubscribe link', function () {
    $spy = fakeResendEmailService();
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'a@example.com']);
    NewsletterSubscriber::factory()->count(2)->create();
    NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com']);

    $post = Post::factory()->published()->create([
        'title' => 'A Great Article',
        'category' => 'Research',
        'excerpt' => 'Read this.',
    ]);

    (new SendBlogNewsletterJob($post))->handle($spy);

    expect($spy->sent)->toHaveCount(3);

    $sent = $spy->sent[0];
    expect($sent['from'])->toBe('info@didn.test')
        ->and($sent['subject'])->toBe('New article: A Great Article')
        ->and($sent['html'])->toContain('A Great Article')
        ->and($sent['html'])->toContain('Research')
        ->and($sent['html'])->toContain('Read this.')
        ->and($sent['html'])->toContain('https://www.didn.test/blog/'.$post->slug)
        ->and($sent['html'])->toContain('/api/v1/newsletter/unsubscribe/'.$subscriber->unsubscribe_token);
});

it('sends the event newsletter with event details and an unsubscribe link', function () {
    $spy = fakeResendEmailService();
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'a@example.com']);
    NewsletterSubscriber::factory()->unsubscribed()->create();

    $event = Event::factory()->published()->create([
        'title' => 'Community Forum',
        'event_type' => 'training',
        'description' => 'A short description.',
        'location' => 'Yenagoa',
        'start_date' => '2026-03-10 09:30:00',
    ]);

    (new SendEventNewsletterJob($event))->handle($spy);

    expect($spy->sent)->toHaveCount(1);

    $sent = $spy->sent[0];
    expect($sent['subject'])->toBe('New event: Community Forum')
        ->and($sent['from'])->toBe('info@didn.test')
        ->and($sent['html'])->toContain('Community Forum')
        ->and($sent['html'])->toContain('training')
        ->and($sent['html'])->toContain('A short description.')
        ->and($sent['html'])->toContain('Yenagoa')
        ->and($sent['html'])->toContain('March 10, 2026')
        ->and($sent['html'])->toContain('9:30 AM')
        ->and($sent['html'])->toContain('https://www.didn.test/events/'.$event->slug)
        ->and($sent['html'])->toContain('/api/v1/newsletter/unsubscribe/'.$subscriber->unsubscribe_token);
});

it('omits the time and location when the event has none', function () {
    $spy = fakeResendEmailService();
    NewsletterSubscriber::factory()->create();
    $event = Event::factory()->published()->create([
        'start_date' => '2026-03-10',
        'location' => null,
    ]);

    (new SendEventNewsletterJob($event))->handle($spy);

    expect($spy->sent[0]['html'])->toContain('March 10, 2026')
        ->not->toContain('12:00 AM')
        ->not->toContain('Location');
});
