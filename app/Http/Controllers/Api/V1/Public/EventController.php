<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Traits\SortsEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use SortsEvents;

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            ->published()
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

    public function show(string $slug): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->with('featuredImage')
            ->where('slug', $slug)
            ->first();

        if (! $event) {
            return $this->error('Event not found.', 404);
        }

        return $this->success(new EventResource($event), 'Event fetched successfully.');
    }
}
