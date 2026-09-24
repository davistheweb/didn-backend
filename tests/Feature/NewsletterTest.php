<?php

use App\Models\NewsletterSubscriber;
use App\Services\Email\NewsletterEmailService;

it('rejects an empty subscribe payload', function () {
    $this->postJson('/api/v1/newsletter/subscribe', [])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['email']]);
});

it('rejects an invalid email address', function () {
    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'nope'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['email']]);
});

it('creates a subscriber and sends a welcome email', function () {
    $spy = fakeResendEmailService();

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'sub@example.com'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', NewsletterEmailService::MESSAGE_CREATED);

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'sub@example.com',
        'status' => 'subscribed',
    ]);

    expect($spy->sent)->toHaveCount(1)
        ->and($spy->sent[0]['to'])->toBe(['sub@example.com'])
        ->and($spy->sent[0]['subject'])->toContain('Welcome')
        ->and($spy->sent[0]['html'])->toContain('/api/v1/newsletter/unsubscribe/');

    $subscriber = NewsletterSubscriber::where('email', 'sub@example.com')->first();
    expect($spy->sent[0]['html'])->toContain($subscriber->unsubscribe_token);
});

it('is idempotent when the address is already subscribed', function () {
    NewsletterSubscriber::factory()->create(['email' => 'sub@example.com']);

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'sub@example.com'])
        ->assertOk()
        ->assertJsonPath('message', NewsletterEmailService::MESSAGE_ALREADY_SUBSCRIBED);

    expect(NewsletterSubscriber::where('email', 'sub@example.com')->count())->toBe(1);
});

it('resubscribes an unsubscribed address with a fresh token', function () {
    $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'sub@example.com']);
    $oldToken = $subscriber->unsubscribe_token;

    $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'sub@example.com'])
        ->assertOk()
        ->assertJsonPath('message', NewsletterEmailService::MESSAGE_RESUBSCRIBED);

    $subscriber->refresh();

    expect($subscriber->status)->toBe(NewsletterSubscriber::STATUS_SUBSCRIBED)
        ->and($subscriber->subscribed_at)->not->toBeNull()
        ->and($subscriber->unsubscribed_at)->toBeNull()
        ->and($subscriber->unsubscribe_token)->not->toBe($oldToken);
});

it('unsubscribes using the token without requiring the email', function () {
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'sub@example.com']);

    $this->getJson('/api/v1/newsletter/unsubscribe/'.$subscriber->unsubscribe_token)
        ->assertOk()
        ->assertJsonPath('message', NewsletterEmailService::MESSAGE_UNSUBSCRIBED);

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'sub@example.com',
        'status' => 'unsubscribed',
        'unsubscribed_at' => now(),
    ]);
});

it('rejects an unknown unsubscribe token', function () {
    $this->getJson('/api/v1/newsletter/unsubscribe/unknown-token')
        ->assertNotFound()
        ->assertJsonPath('message', NewsletterEmailService::MESSAGE_INVALID_TOKEN);
});
