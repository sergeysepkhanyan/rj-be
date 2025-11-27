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

    public function update($id, array $data)
    {
        $post = Post::findOrFail($id);
        $post->update($data);
        return $post;
    }

    public function delete($id)
    {
        $post = Post::findOrFail($id);
        return $post->delete();
    }

    public function paginated(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return Post::orderBy('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByUrlSlug(string $slug)
    {
        return Post::where('slug', $slug)->firstOrFail();
    }
}

