<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $status = in_array($request->input('status'), NewsletterSubscriber::STATUSES, true)
            ? $request->input('status')
            : null;

        $subscribers = NewsletterSubscriber::query()
            ->search($request->input('search'))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($this->resolvedPerPage($request, 50, 100));

        return $this->list(
            NewsletterSubscriberResource::collection($subscribers),
            $this->paginationMeta($subscribers),
            'Subscribers fetched successfully.',
        );
    }

    public function show(NewsletterSubscriber $subscriber): JsonResponse
    {
        return $this->success(new NewsletterSubscriberResource($subscriber), 'Subscriber fetched successfully.');
    }

    public function destroy(NewsletterSubscriber $subscriber): JsonResponse
    {
        $subscriber->unsubscribe();

        return $this->success(null, 'Subscriber unsubscribed successfully.');
    }
}
