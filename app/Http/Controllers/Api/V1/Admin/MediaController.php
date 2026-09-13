<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function index(Request $request): JsonResponse
    {
        $media = Media::query()
            ->latest()
            ->paginate($this->resolvedPerPage($request, 50, 100));

        return $this->list(
            MediaResource::collection($media),
            $this->paginationMeta($media),
            'Media fetched successfully.',
        );
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $media = $this->mediaService->store($request->file('file'));

        return $this->success(new MediaResource($media), 'Media uploaded successfully.', 201);
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->mediaService->delete($media);

        return $this->success(null, 'Media deleted successfully.');
    }
}
