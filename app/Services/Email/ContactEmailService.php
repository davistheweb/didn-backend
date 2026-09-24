<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;
use Throwable;

class ContactEmailService
{
    public function __construct(
        private readonly ResendEmailService $resend,
    ) {}

    /**
     * Send a contact-form notification to the configured admin inbox.
     *
     * Uses a trusted DIDN address as the From and the visitor's address as the
     * Reply-To. Failures are logged so an email outage never breaks the API
     * response for the visitor.
     *
     * @param  array{full_name: string, phone_number: string, email: string, message: string}  $data
     */
    public function send(array $data): void
    {
        $recipient = config('services.resend.contact_notification_email')
            ?? config('services.resend.from_address');

        if (blank($recipient)) {
            Log::warning('Contact notification skipped: no recipient email configured.');

            return;
        }

        $html = view('emails.contact', [
            'fullName' => $data['full_name'],
            'phoneNumber' => $data['phone_number'],
            'email' => $data['email'],
            'message' => $data['message'],
        ])->render();

        try {
            $this->resend->send(
                to: [$recipient],
                subject: 'New Contact Form Message from '.$data['full_name'],
                html: $html,
                from: config('services.resend.contact_from_address'),
                replyTo: [$data['email']],
            );
        } catch (Throwable $e) {
            Log::error('Failed to send contact notification.', [
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
