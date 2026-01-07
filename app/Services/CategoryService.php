<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryService
{

    public function __construct(protected CategoryRepositoryInterface $categoryRepository){}

    public function getAllCategories(array $filters = [])
    {
        return $this->categoryRepository->all($filters);
    }

    public function getCategoryById($id)
    {
        return $this->categoryRepository->find($id);
    }

    public function createCategory(array $data)
    {
        return $this->categoryRepository->create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        return $this->categoryRepository->update($category, $data);
    }

    public function deleteCategory(Category $category): ?bool
    {
        return $this->categoryRepository->delete($category);
    }

    public function getPaginatedCategories($filter = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->categoryRepository->paginateWithFilter($filter, $perPage);
    }

    public function getByGender(string $gender = 'Female'): \Illuminate\Database\Eloquent\Collection
    {
        return $this->categoryRepository->getByGender($gender);
    }

}
