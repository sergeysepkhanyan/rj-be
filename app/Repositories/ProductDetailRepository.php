<?php

namespace App\Repositories;

use App\Models\ProductDetail;
use App\Repositories\Interfaces\ProductDetailRepositoryInterface;

class ProductDetailRepository implements ProductDetailRepositoryInterface
{
    public function all()
    {
        return ProductDetail::all();
    }

    public function find($id)
    {
        return ProductDetail::findOrFail($id);
    }

    public function create(array $data)
    {
        return ProductDetail::create($data);
    }

    public function update($id, array $data)
    {
        $productDetail = ProductDetail::findOrFail($id);
        $productDetail->update($data);
        return $productDetail;
    }

    public function delete($id)
    {
        $productDetail = ProductDetail::findOrFail($id);
        return $productDetail->delete();
    }

    public function createForProduct($product, array $data): ProductDetail
    {
        return $product->details()->create($data);
    }

    public function createMultipleForProduct($product, array $details): array
    {
        $created = [];
        foreach ($details as $detail) {
            $created[] = $this->createForProduct($product, $detail);
        }
        return $created;
    }
}

