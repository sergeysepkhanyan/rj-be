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
    protected PostService $postService;

    public function __construct(PostService $postService)
    {
        $this->postService = $postService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $posts = $this->postService->getPaginatedPosts($perPage, $page);

            return ApiResponse::success(
                [
                    'posts' => PostResource::collection($posts),
                    'meta' => [
                        'current_page' => $posts->currentPage(),
                        'last_page' => $posts->lastPage(),
                        'per_page' => $posts->perPage(),
                        'total' => $posts->total(),
                    ],
                    'links' => [
                        'first' => $posts->url(1),
                        'last' => $posts->url($posts->lastPage()),
                        'prev' => $posts->previousPageUrl(),
                        'next' => $posts->nextPageUrl(),
                    ],
                ],
                'Posts retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function getBySlug(string $slug): JsonResponse
    {

        try {
            $post = $this->postService->getBySlug($slug);
            return ApiResponse::success(
                [
                    'post' => new PostResource($post)
                ],
                'Post selected successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
