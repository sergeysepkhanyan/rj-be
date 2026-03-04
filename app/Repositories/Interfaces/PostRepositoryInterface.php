<?php

namespace App\Repositories\Interfaces;

use App\Models\Post;
use Illuminate\Pagination\LengthAwarePaginator;

interface PostRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Post $post, array $data);
    public function delete(Post $post);

    /**
     * Get paginated posts for public view (only Published posts)
     */
    public function paginated($lang = 'en', int $perPage = 15, int $page = 1): LengthAwarePaginator;

    /**
     * Get paginated posts for admin view (all statuses)
     */
    public function paginatedAdmin($lang = null, int $perPage = 15, int $page = 1): LengthAwarePaginator;

    /**
     * Find post by slug (only Published for public)
     */
    public function findByUrlSlug(string $slug);

    /**
     * Find post by slug (any status for admin)
     */
    public function findByUrlSlugAdmin(string $slug);
}
