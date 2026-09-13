<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\RichTextSanitizer;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private readonly SlugService $slugs,
        private readonly RichTextSanitizer $sanitizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $allowedStatuses = Post::STATUSES;
        $status = in_array($request->input('status'), $allowedStatuses, true)
            ? $request->input('status')
            : null;

        $query = Post::query()
            ->with(['author', 'coverImage'])
            ->ofCategory($request->input('category'))
            ->search($request->input('search'))
            ->when($status, fn ($q) => $q->where('status', $status));

        $sortBy = in_array($request->input('sort_by'), ['published_at', 'created_at', 'title'], true)
            ? $request->input('sort_by')
            : 'created_at';

        $sortDir = strtolower((string) $request->input('sort_dir')) === 'desc' ? 'desc' : 'asc';

        $posts = $query->orderBy($sortBy, $sortDir)->paginate($this->resolvedPerPage($request));

        return $this->list(
            PostResource::collection($posts),
            $this->paginationMeta($posts),
            'Posts fetched successfully.',
        );
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::create([
            ...$request->validated(),
            'slug' => $this->slugs->unique(
                $request->validated('slug') ?? $request->validated('title'),
                Post::class,
            ),
            'content' => $this->sanitizer->sanitize($request->validated('content')),
            'author_id' => $request->user()->id,
        ]);

        $post->load(['author', 'coverImage']);

        return $this->success(new PostResource($post), 'Post created successfully.', 201);
    }

    public function show(Post $post): JsonResponse
    {
        $post->load(['author', 'coverImage']);

        return $this->success(new PostResource($post), 'Post fetched successfully.');
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('slug', $data) && $data['slug'] !== $post->slug) {
            $data['slug'] = $this->slugs->unique($data['slug'] ?? $post->title, Post::class, $post->id);
        }

        if (array_key_exists('content', $data)) {
            $data['content'] = $this->sanitizer->sanitize($data['content']);
        }

        $post->update($data);
        $post->load(['author', 'coverImage']);

        return $this->success(new PostResource($post), 'Post updated successfully.');
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return $this->success(null, 'Post deleted successfully.');
    }
}
