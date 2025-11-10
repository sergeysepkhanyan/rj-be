<?php

namespace App\Services;

use App\Repositories\FileRepository;
use App\Repositories\Interfaces\ProductDetailRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;


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
        return DB::transaction(function () use ($productData, $detailsData, $productFilePaths) {
            $product = $this->productRepository->create($productData);

            if ($productFilePaths) {
                $this->fileRepository->createMultipleForFileable($product, $productFilePaths);
            }

            foreach ($detailsData as $detail) {
                $this->productDetailRepository->createForProduct($product, $detail);
            }

            return $product->load('details', 'files');
        });
    }

    public function updateProduct(
        int $id,
        array $productData,
        array $detailsData = [],
        array $newFiles = [],
        array $removedFiles = []
    )
    {
        return DB::transaction(function () use ($productData, $detailsData, $newFiles, $removedFiles, $id) {
            $product = $this->getProductById($id);
            $product->update($productData);
            if ($removedFiles) {
                $this->fileRepository->deleteByPaths($product, $removedFiles);
            }
            if ($newFiles) {
                $this->fileRepository->createMultipleForFileable($product, $newFiles);
            }
            $existingDetailIds = $product->details()->pluck('id')->toArray();
            $incomingDetailIds = array_filter(array_column($detailsData, 'id'));
            $detailsToDelete = array_diff($existingDetailIds, $incomingDetailIds);
            if ($detailsToDelete) {
                $this->productDetailRepository->deleteByIds($detailsToDelete);
            }
            foreach ($detailsData as $detail) {
                if (!empty($detail['id']) && in_array($detail['id'], $existingDetailIds)) {
                    $this->productDetailRepository->updateForProduct($product, $detail['id'], $detail);
                } else {
                    $this->productDetailRepository->createForProduct($product, $detail);
                }
            }
            return $product->load('details', 'files');
        });
    }



    public function getPaginatedProducts(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->productRepository->paginateStaff($perPage, $page);
    }

    public function getProductById($id)
    {
        return $this->productRepository->find($id);
    }
}
