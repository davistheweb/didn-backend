<?php

use App\Services\Email\ResendEmailService;

it('rejects an empty contact payload', function () {
    $this->postJson('/api/v1/contact', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['full_name', 'phone_number', 'email', 'message']]);
});

it('rejects an invalid email address', function () {
    $this->postJson('/api/v1/contact', [
        'full_name' => 'John Doe',
        'phone_number' => '08012345678',
        'email' => 'not-an-email',
        'message' => 'Hello.',
    ])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['email']]);
});

it('sends a contact notification with the visitor as reply-to', function () {
    $spy = fakeResendEmailService();

    $this->postJson('/api/v1/contact', [
        'full_name' => 'Jane Okoro',
        'phone_number' => '+2348012345678',
        'email' => 'jane@example.com',
        'message' => 'I would like to volunteer.',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Your message has been sent successfully.');

    expect($spy->sent)->toHaveCount(1);

    $sent = $spy->sent[0];
    expect($sent['from'])->toBe('contact@didn.test')
        ->not->toBe('jane@example.com')
        ->and($sent['to'])->toBe(['admin@didn.test'])
        ->and($sent['subject'])->toBe('New Contact Form Message from Jane Okoro')
        ->and($sent['replyTo'])->toBe(['jane@example.com'])
        ->and($sent['html'])->toContain('Jane Okoro')
        ->and($sent['html'])->toContain('+2348012345678')
        ->and($sent['html'])->toContain('I would like to volunteer.');
});

it('still returns success when the notification fails to send', function () {
    $failing = new class extends ResendEmailService
    {
        public string $failedWith = 'boom';

        public function send(array $to, string $subject, string $html, ?string $from = null, ?array $replyTo = null): void
        {
            throw new RuntimeException($this->failedWith);
        }
    };

    app()->instance(ResendEmailService::class, $failing);

    $this->postJson('/api/v1/contact', [
        'full_name' => 'Jane Okoro',
        'phone_number' => '08012345678',
        'email' => 'jane@example.com',
        'message' => 'Hello.',
    ])->assertOk();
});
