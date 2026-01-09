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
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new Post)->getFillable()));
        $post = $this->postService->createPost($data);
        return ApiResponse::success([
            'post' => new PostResource($post),
        ], 'Post created successfully');
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new Post)->getFillable()));
        $post = $this->postService->updatePost($post, $data);
        return ApiResponse::success([
            'post' => new PostResource($post),
        ], 'Post updated successfully');
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->postService->deletePost($post);

        return ApiResponse::success([
            'success' => true,
        ], 'Post deleted successfully');
    }
}
