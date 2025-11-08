<?php

namespace App\Services;

use App\Repositories\FileRepository;
use App\Repositories\Interfaces\ProductDetailRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;


class ProductService
{
    protected ProductRepositoryInterface $productRepository;
    protected ProductDetailRepositoryInterface $productDetailRepository;
    protected FileRepository $fileRepository;

    public function __construct(
        ProductRepositoryInterface       $productRepository,
        ProductDetailRepositoryInterface $productDetailRepository,
        FileRepository                   $fileRepository
    )
    {
        $this->productRepository = $productRepository;
        $this->productDetailRepository = $productDetailRepository;
        $this->fileRepository = $fileRepository;
    }

    public function createProduct(array $productData, array $detailsData = [], array $productFilePaths = [])
    {
        $product = $this->productRepository->create($productData);

        if ($productFilePaths) {
            $this->fileRepository->createMultipleForFileable($product, $productFilePaths);
        }

        foreach ($detailsData as $detail) {
            $this->productDetailRepository->createForProduct($product, $detail);
        }

        return $product->load('details', 'files');
    }

    public function getPaginatedProducts(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->productRepository->paginateStaff($perPage, $page);
    }
}
