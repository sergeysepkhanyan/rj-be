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

    public function paginated($lang = 'en', int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return Post::orderBy('created_at')->where('lang', $lang)->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByUrlSlug(string $slug)
    {
        return Post::where('slug', $slug)->firstOrFail();
    }
}

