<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\NewsletterSubscriber;
use App\Services\Email\ResendEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEventNewsletterJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Event $event,
    ) {}

    public function handle(ResendEmailService $resend): void
    {
        $publicWebsiteUrl = rtrim((string) config('services.resend.public_website_url', 'https://www.directimpactnetwork.org'), '/');
        $startDate = $this->event->start_date;
        $time = $startDate !== null && $startDate->format('Hi') !== '0000' ? $startDate->format('g:i A') : null;

        NewsletterSubscriber::query()
            ->active()
            ->chunkById(100, function ($subscribers) use ($resend, $publicWebsiteUrl, $startDate, $time) {
                foreach ($subscribers as $subscriber) {
                    try {
                        $resend->send(
                            to: [$subscriber->email],
                            subject: 'New event: '.$this->event->title,
                            html: view('emails.event-published', [
                                'eventType' => $this->event->event_type,
                                'title' => $this->event->title,
                                'description' => $this->event->description,
                                'date' => $startDate?->format('F j, Y'),
                                'time' => $time,
                                'location' => $this->event->location,
                                'eventUrl' => $publicWebsiteUrl.'/events/'.$this->event->slug,
                                'unsubscribeUrl' => $resend->unsubscribeUrl($subscriber->unsubscribe_token),
                            ])->render(),
                        );
                    } catch (Throwable $e) {
                        Log::error('Failed to send event newsletter email.', [
                            'subscriber' => $subscriber->email,
                            'event' => $this->event->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
