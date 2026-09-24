<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;
use Resend;

class ResendEmailService
{
    /**
     * Send an HTML email through Resend.
     *
     * The recipient list is always trusted (a configured admin address or a
     * stored subscriber address). A missing API key logs a warning and skips
     * the send so development environments fail softly; a failed API call
     * throws so callers can log and continue.
     *
     * @param  list<string>  $to
     * @param  list<string>  $replyTo
     */
    public function send(array $to, string $subject, string $html, ?string $from = null, ?array $replyTo = null): void
    {
        if (blank(config('services.resend.key'))) {
            Log::warning('Resend email skipped: RESEND_API_KEY is not configured.', [
                'to' => $to,
                'subject' => $subject,
            ]);

            return;
        }

        $payload = [
            'from' => $from ?? config('services.resend.from_address'),
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
        ];

        if ($replyTo !== null) {
            $payload['reply_to'] = $replyTo;
        }

        /**
         * The SDK exposes Resend::client() on a global class, while its
         * composer mapping also resolves the namespaced Resend\Resend to the
         * same file. Calling the namespaced alias re-includes that file and
         * fatals with "Cannot redeclare class Resend", so we use the global
         * class—the same entry point Laravel's own Resend transport uses.
         */
        Resend::client(config('services.resend.key'))->emails->send($payload);
    }

    /**
     * Build the absolute unsubscribe URL for a subscriber token.
     *
     * Uses the application URL so it is stable regardless of where the email
     * is rendered.
     */
    public function unsubscribeUrl(string $token): string
    {
        return url('/api/v1/newsletter/unsubscribe/'.$token);
    }
}
