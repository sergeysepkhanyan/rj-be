<?php

namespace App\Repositories;

use App\Models\ProductCategory;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;

class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function all()
    {
        return ProductCategory::query()->orderBy('name')->get();
    }

    public function find(int $id): ?ProductCategory
    {
        return ProductCategory::find($id);
    }

    public function firstOrCreateByName(string $name): ProductCategory
    {
        return ProductCategory::firstOrCreate(['name' => $name]);
    }
}
