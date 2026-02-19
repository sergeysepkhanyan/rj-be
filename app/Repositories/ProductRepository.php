<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Filters\ProductFilter;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository implements ProductRepositoryInterface
{
    public function all()
    {
        return Product::all();
    }

    public function allForExport(?array $ids = null)
    {
        return Product::with(['productCategory', 'supplier'])
            ->when($ids && count($ids) > 0, function ($query) use ($ids) {
                $query->whereIn('id', $ids);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function find($id)
    {
        return Product::findOrFail($id);
    }

    public function findBySlug(string $slug): ?Product
    {
        return Product::where('slug', $slug)
            ->where('status', 'active')
            ->first();
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

    public function paginateProducts(?ProductFilter $filter = null, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Product::with(['details', 'files', 'productCategory', 'supplier'])
            ->orderByDesc('created_at');

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}

