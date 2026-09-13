<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()
            ->published()
            ->with(['author', 'coverImage'])
            ->ofCategory($request->input('category'))
            ->search($request->input('search'));

        $sortBy = in_array($request->input('sort_by'), ['published_at', 'created_at', 'title'], true)
            ? $request->input('sort_by')
            : 'published_at';

        $sortDir = strtolower((string) $request->input('sort_dir')) === 'desc' ? 'desc' : 'asc';

        $posts = $query->orderBy($sortBy, $sortDir)->paginate($this->resolvedPerPage($request, 9));

        return $this->list(
            PostResource::collection($posts),
            $this->paginationMeta($posts),
            'Posts fetched successfully.',
        );
    }

    public function show(string $slug): JsonResponse
    {
        $post = Post::query()
            ->published()
            ->with(['author', 'coverImage'])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            return $this->error('Post not found.', 404);
        }

        return $this->success(new PostResource($post), 'Post fetched successfully.');
    }
}
