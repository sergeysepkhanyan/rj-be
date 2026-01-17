<?php

namespace App\Repositories\Interfaces;

use App\Models\ProductCategory;

interface ProductCategoryRepositoryInterface
{
    public function find(int $id): ?ProductCategory;
    public function firstOrCreateByName(string $name): ProductCategory;
    public function all();
}
