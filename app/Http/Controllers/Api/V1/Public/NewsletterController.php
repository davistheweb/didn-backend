<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\SubscribeRequest;
use App\Services\Email\NewsletterEmailService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NewsletterEmailService $newsletter,
    ) {}

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $result = $this->newsletter->subscribe($request->validated('email'));

        $message = match ($result['status']) {
            'already_subscribed' => NewsletterEmailService::MESSAGE_ALREADY_SUBSCRIBED,
            'resubscribed' => NewsletterEmailService::MESSAGE_RESUBSCRIBED,
            default => NewsletterEmailService::MESSAGE_CREATED,
        };

        return $this->success(null, $message);
    }

    public function unsubscribe(string $token): JsonResponse
    {
        $subscriber = $this->newsletter->unsubscribeByToken($token);

        if ($subscriber === null) {
            return $this->error(NewsletterEmailService::MESSAGE_INVALID_TOKEN, 404);
        }

        return $this->success(null, NewsletterEmailService::MESSAGE_UNSUBSCRIBED);
    }
}
