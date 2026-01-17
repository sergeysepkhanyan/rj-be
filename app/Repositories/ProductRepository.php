<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function all()
    {
        return Product::all();
    }

    public function find($id)
    {
        return Product::findOrFail($id);
    }

    public function create(array $data)
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product;
    }

    public function delete(Product $product): ?bool
    {
        return $product->delete();
    }

    public function paginateProducts(int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return Product::with(['details', 'files', 'productCategory'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}

