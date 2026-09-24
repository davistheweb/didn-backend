<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Services\Email\ResendEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendBlogNewsletterJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Post $post,
    ) {}

    public function handle(ResendEmailService $resend): void
    {
        $publicWebsiteUrl = rtrim((string) config('services.resend.public_website_url', 'https://www.directimpactnetwork.org'), '/');

        NewsletterSubscriber::query()
            ->active()
            ->chunkById(100, function ($subscribers) use ($resend, $publicWebsiteUrl) {
                foreach ($subscribers as $subscriber) {
                    try {
                        $resend->send(
                            to: [$subscriber->email],
                            subject: 'New article: '.$this->post->title,
                            html: view('emails.blog-published', [
                                'category' => $this->post->category,
                                'title' => $this->post->title,
                                'excerpt' => $this->post->excerpt,
                                'coverImage' => $this->post->coverImage?->url,
                                'articleUrl' => $publicWebsiteUrl.'/blog/'.$this->post->slug,
                                'unsubscribeUrl' => $resend->unsubscribeUrl($subscriber->unsubscribe_token),
                            ])->render(),
                        );
                    } catch (\Throwable $e) {
                        Log::error('Failed to send blog newsletter email.', [
                            'subscriber' => $subscriber->email,
                            'post' => $this->post->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
