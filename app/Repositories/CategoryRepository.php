<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function all(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {

        $query = Category::query()->with(['services.subServices.items', 'services.files']);

        return $query->when(!empty($filters['id']), function ($query) use ($filters) {
            $query->where('id', (int) $filters['id']);
        })
            ->when(!empty($filters['name']), function ($query) use ($filters) {
                $name = trim((string) $filters['name']);
                if ($name !== '') {
                    $query->where('name', 'LIKE', "%{$name}%");
                }
            })->get();    }

    public function find($id)
    {
        return Category::with(['services.subServices.items', 'services.files'])->findOrFail($id);
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

    public function getByGender(string $gender = 'Female'): \Illuminate\Database\Eloquent\Collection
    {
        $query = Category::query()->where('gender', $gender)->with(['services.subServices.items', 'services.files']);

        return $query->get();
    }

}
