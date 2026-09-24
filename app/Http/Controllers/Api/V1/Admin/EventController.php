<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Jobs\SendEventNewsletterJob;
use App\Models\Event;
use App\Services\MediaService;
use App\Services\RichTextSanitizer;
use App\Services\SlugService;
use App\Traits\SortsEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use SortsEvents;

    public function __construct(
        private readonly SlugService $slugs,
        private readonly RichTextSanitizer $sanitizer,
        private readonly MediaService $mediaService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            ->with('featuredImage')
            ->ofType($request->input('type'));

        $status = $request->input('status');

        if ($status === Event::STATUS_UPCOMING) {
            $query->upcoming();
        } elseif ($status === Event::STATUS_PAST) {
            $query->past();
        }

        $this->applyEventSort($query, $request, $status);

        $events = $query->paginate($this->resolvedPerPage($request));

        return $this->list(
            EventResource::collection($events),
            $this->paginationMeta($events),
            'Events fetched successfully.',
        );
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = Event::create([
            ...$request->validated(),
            'slug' => $this->slugs->unique(
                $request->validated('slug') ?? $request->validated('title'),
                Event::class,
            ),
            'content' => $this->sanitizer->sanitize($request->validated('content')),
        ]);

        $event->load('featuredImage');

        $this->mediaService->reconcileSlugFolder(MediaService::CONTEXT_EVENT, $event);

        if ($event->is_published) {
            SendEventNewsletterJob::dispatch($event);
        }

        return $this->success(new EventResource($event), 'Event created successfully.', 201);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load('featuredImage');

        return $this->success(new EventResource($event), 'Event fetched successfully.');
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('slug', $data) && $data['slug'] !== $event->slug) {
            $data['slug'] = $this->slugs->unique($data['slug'] ?? $event->title, Event::class, $event->id);
        }

        if (array_key_exists('content', $data)) {
            $data['content'] = $this->sanitizer->sanitize($data['content']);
        }

        $previousFeatured = $event->featuredImage;

        $event->update($data);

        $event->unsetRelation('featuredImage');

        $this->mediaService->reconcileSlugFolder(MediaService::CONTEXT_EVENT, $event);

        if ($previousFeatured !== null && $event->featured_image_id !== $previousFeatured->id) {
            $this->mediaService->deleteCoverIfUnreferenced($previousFeatured, $event);
        }

        $event->load('featuredImage');

        if ($event->wasChanged('is_published') && $event->is_published) {
            SendEventNewsletterJob::dispatch($event);
        }

        return $this->success(new EventResource($event), 'Event updated successfully.');
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        $this->mediaService->deleteContentDirectory(MediaService::CONTEXT_EVENT, $event);

        return $this->success(null, 'Event deleted successfully.');
    }
}
