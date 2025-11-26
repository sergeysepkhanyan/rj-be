<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
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

    public function store(CreatePostRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $post = $this->postService->createPost($data);
            return ApiResponse::success([
                'post' => new PostResource($post),
            ], 'Post created successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error();
        }
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        try {
            $data = $request->validated();
            $post = $this->postService->updatePost($post->id, $data);
            return ApiResponse::success([
                'post' => new PostResource($post),
            ], 'Post updated successfully');
        } catch (\Throwable $th) {
            return ApiResponse::error();
        }
    }
}
