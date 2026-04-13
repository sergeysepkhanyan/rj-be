<?php

namespace App\Repositories\Interfaces;

use App\Models\Product;

interface ProductRepositoryInterface
{
    public function all();
    public function find($id);
    public function findBySlug(string $slug): ?Product;
    public function create(array $data);
    public function update(Product $product, array $data): Product;
    public function delete(Product $product);
    public function allForExport(?array $ids = null);
    public function paginateProducts(?\App\Filters\ProductFilter $filter = null, int $perPage = 10, int $page = 1): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
}
