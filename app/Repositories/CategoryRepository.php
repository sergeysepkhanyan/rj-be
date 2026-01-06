<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function all()
    {
        return Category::all();
    }

    public function find($id)
    {
        return Category::findOrFail($id);
    }

    public function create(array $data)
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);
        return $category;
    }

    public function delete(Category $category): ?bool
    {
        return $category->delete();
    }

    public function paginateWithFilter($filter = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Category::query();

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->paginate($perPage);
    }

}
