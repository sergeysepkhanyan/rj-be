<?php

namespace App\Services;

use App\Models\ProductCategory;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;

class ProductCategoryService
{
    public function __construct(
        protected ProductCategoryRepositoryInterface $productCategoryRepository
    ) {}

    public function list()
    {
        return $this->productCategoryRepository->all();
    }

    public function findById(int $id): ?ProductCategory
    {
        return $this->productCategoryRepository->find($id);
    }

    public function firstOrCreateByName(string $name): ProductCategory
    {
        return $this->productCategoryRepository->firstOrCreateByName($name);
    }
}
