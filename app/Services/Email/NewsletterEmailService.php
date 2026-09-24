<?php

namespace App\Services\Email;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsletterEmailService
{
    public function __construct(
        private readonly ResendEmailService $resend,
    ) {}

    public const MESSAGE_CREATED = 'You have been subscribed successfully!';

    public const MESSAGE_ALREADY_SUBSCRIBED = "You're already subscribed.";

    public const MESSAGE_RESUBSCRIBED = 'Welcome back! You have been resubscribed successfully.';

    public const MESSAGE_UNSUBSCRIBED = 'You have been unsubscribed from the newsletter.';

    public const MESSAGE_INVALID_TOKEN = 'This unsubscribe link is invalid.';

    /**
     * Subscribe an email address, keeping the operation idempotent.
     *
     * Existing subscribers stay untouched; unsubscribed addresses are
     * reactivated with a fresh token and a new welcome email.
     *
     * @return array{status: string, subscriber: NewsletterSubscriber}
     */
    public function subscribe(string $email): array
    {
        $subscriber = NewsletterSubscriber::firstWhere('email', $email);

        if ($subscriber === null) {
            $subscriber = NewsletterSubscriber::create([
                'email' => $email,
                'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
                'subscribed_at' => now(),
            ]);

            $status = 'created';
        } elseif ($subscriber->isSubscribed()) {
            return [
                'status' => 'already_subscribed',
                'subscriber' => $subscriber,
            ];
        } else {
            $subscriber->subscribe();
            $status = 'resubscribed';
        }

        $this->sendWelcome($subscriber);

        return [
            'status' => $status,
            'subscriber' => $subscriber,
        ];
    }

    /**
     * Unsubscribe by the link token. The token is the credential, so the
     * subscriber's email is never required on the public unsubscribe route.
     */
    public function unsubscribeByToken(string $token): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::firstWhere('unsubscribe_token', $token);

        $subscriber?->unsubscribe();

        return $subscriber;
    }

    /**
     * Unsubscribe by email, used by the admin endpoints.
     */
    public function unsubscribeByEmail(string $email): bool
    {
        $subscriber = NewsletterSubscriber::firstWhere('email', $email);

        if ($subscriber === null || ! $subscriber->isSubscribed()) {
            return false;
        }

        $subscriber->unsubscribe();

        return true;
    }

    public function sendWelcome(NewsletterSubscriber $subscriber): void
    {
        $html = view('emails.newsletter-welcome', [
            'subject' => 'Welcome to the Direct Impact Development Network newsletter',
            'unsubscribeUrl' => $this->resend->unsubscribeUrl($subscriber->unsubscribe_token),
        ])->render();

        try {
            $this->resend->send(
                to: [$subscriber->email],
                subject: 'Welcome to the Direct Impact Development Network newsletter',
                html: $html,
            );
        } catch (Throwable $e) {
            Log::error('Failed to send newsletter welcome email.', [
                'subscriber' => $subscriber->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
