<?php

namespace App\Services\Email;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminAlertEmailService
{
    public function __construct(
        private readonly ResendEmailService $resend,
    ) {}

    /**
     * Notify the admin inbox when a blog post has been published.
     *
     * Sent synchronously so the admin sees the confirmation immediately,
     * independent of the queued subscriber blast.
     */
    public function postPublished(Post $post): void
    {
        $publicWebsiteUrl = rtrim((string) config('services.resend.public_website_url', 'https://www.directimpactnetwork.org'), '/');

        $html = view('emails.admin-post-published', [
            'category' => $post->category,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'coverImage' => $post->coverImage?->url,
            'articleUrl' => $publicWebsiteUrl.'/blog/'.$post->slug,
        ])->render();

        $this->sendToAdminInbox('New blog post published: '.$post->title, $html);
    }

    /**
     * Notify the admin inbox when an event has been published.
     */
    public function eventPublished(Event $event): void
    {
        $publicWebsiteUrl = rtrim((string) config('services.resend.public_website_url', 'https://www.directimpactnetwork.org'), '/');
        $startDate = $event->start_date;
        $time = $startDate !== null && $startDate->format('Hi') !== '0000' ? $startDate->format('g:i A') : null;

        $html = view('emails.admin-event-published', [
            'eventType' => $event->event_type,
            'title' => $event->title,
            'description' => $event->description,
            'date' => $startDate?->format('F j, Y'),
            'time' => $time,
            'location' => $event->location,
            'eventUrl' => $publicWebsiteUrl.'/events/'.$event->slug,
        ])->render();

        $this->sendToAdminInbox('New event published: '.$event->title, $html);
    }

    /**
     * The admin inbox is the contact-notification address with a fallback to
     * the primary sender address.
     */
    private function sendToAdminInbox(string $subject, string $html): void
    {
        $recipient = config('services.resend.contact_notification_email')
            ?? config('services.resend.from_address');

        if (blank($recipient)) {
            Log::warning('Admin alert skipped: no recipient email configured.');

            return;
        }

        try {
            $this->resend->send(
                to: [$recipient],
                subject: $subject,
                html: $html,
            );
        } catch (Throwable $e) {
            Log::error('Failed to send admin publish alert.', [
                'recipient' => $recipient,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
