<?php

namespace App\Repositories;

use App\Models\Post;
use App\Repositories\Interfaces\PostRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class PostRepository implements PostRepositoryInterface
{
    public function all()
    {
        return Post::all();
    }

    public function find($id)
    {
        return Post::findOrFail($id);
    }

    public function create(array $data)
    {
        return Post::create($data);
    }

    public function update(Post $post, array $data): Post
    {
        $post->update($data);
        return $post;
    }

    public function delete(Post $post): ?bool
    {
        return $post->delete();
    }

    /**
     * Get paginated posts for public view (only Published posts)
     */
    public function paginated($lang = 'en', int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return Post::where('lang', $lang)
            ->where('status', 'Published')
            ->orderByDesc('publish_date')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get paginated posts for admin view (all statuses)
     */
    public function paginatedAdmin($lang = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = Post::query();

        if ($lang) {
            $query->where('lang', $lang);
        }

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Find post by slug (only Published for public)
     */
    public function findByUrlSlug(string $slug)
    {
        return Post::where('slug', $slug)
            ->where('status', 'Published')
            ->first();
    }

    /**
     * Find post by slug (any status for admin)
     */
    public function findByUrlSlugAdmin(string $slug)
    {
        return Post::where('slug', $slug)->first();
    }
}

