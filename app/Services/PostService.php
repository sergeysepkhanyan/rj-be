<?php

namespace App\Services;

use App\Models\Post;
use App\Repositories\Interfaces\PostRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class PostService
{
    protected PostRepositoryInterface $postRepository;

    public function __construct(
        PostRepositoryInterface $postRepository,
    )
    {
        $this->postRepository = $postRepository;
    }

    public function getAllPosts()
    {
        return $this->postRepository->all();
    }

    public function createPost(array $data)
    {
        return $this->postRepository->create($data);
    }

    public function updatePost(Post $post, array $data)
    {
        return $this->postRepository->update($post, $data);
    }

    public function deletePost(Post $post): bool
    {
        return $this->postRepository->delete($post);
    }


    public function getPaginatedPosts($lang = 'en',int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->postRepository->paginated($lang, $perPage, $page);
    }

    public function getBySlug(string $slug)
    {
        return $this->postRepository->findByUrlSlug($slug);
    }
}

