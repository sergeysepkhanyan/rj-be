<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Services\ApiResponse;
use App\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostsController extends Controller
{
    public function __construct(protected PostService $postService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $page    = (int) $request->get('page', 1);
        $lang = app('api_locale') ?? $request->header('Accept-Language', 'en');

        $posts = $this->postService->getPaginatedPosts($lang, $perPage, $page);

        return ApiResponse::success([
            'posts' => PostResource::collection($posts),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page'    => $posts->lastPage(),
                'per_page'     => $posts->perPage(),
                'total'        => $posts->total(),
            ],
            'links' => [
                'first' => $posts->url(1),
                'last'  => $posts->url($posts->lastPage()),
                'prev'  => $posts->previousPageUrl(),
                'next'  => $posts->nextPageUrl(),
            ],
        ], __('success.posts.listed'));
    }

    public function getBySlug(string $slug): JsonResponse
    {
        $post = $this->postService->getBySlug($slug);

        if (!$post) {
            return ApiResponse::error(null, __('errors.post.not_found'), 404);
        }

        return ApiResponse::success([
            'post' => new PostResource($post)
        ], __('success.posts.selected'));
    }
}

