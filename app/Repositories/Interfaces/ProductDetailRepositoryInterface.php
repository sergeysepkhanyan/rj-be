<?php

namespace App\Repositories\Interfaces;

use App\Models\ProductDetail;

interface ProductDetailRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(ProductDetail $productDetail, array $data): ProductDetail;
    public function delete(ProductDetail $productDetail);
    public function createForProduct($product, array $data): ProductDetail;
    public function createMultipleForProduct($product, array $details): array;
    public function updateForProduct($product, int $detailId, array $detailData);
    public function deleteByIds(array $detailIds): void;
}
