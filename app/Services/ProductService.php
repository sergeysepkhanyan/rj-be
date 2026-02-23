<?php

namespace App\Services;

use App\Filters\ProductFilter;
use App\Jobs\Zoho\SyncProductToZohoJob;
use App\Models\Product;
use App\Repositories\FileRepository;
use App\Repositories\Interfaces\ProductDetailRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;


class ProductService
{
    public function __construct(
      protected ProductRepositoryInterface       $productRepository,
      protected ProductDetailRepositoryInterface $productDetailRepository,
      protected FileRepository                   $fileRepository
    ){}

    public function createProduct(array $productData, array $detailsData = [], array $productFilePaths = [])
    {
        return DB::transaction(function () use ($productData, $detailsData, $productFilePaths) {
            if (!empty($productData['sku_id'])) {
                $existingProduct = Product::where('sku_id', $productData['sku_id'])->first();
                if ($existingProduct) {
                    throw new \Illuminate\Validation\ValidationException(
                        \Illuminate\Support\Facades\Validator::make([], []),
                        ['skuId' => [__('validation_scoped.product.skuId.unique')]]
                    );
                }
            }
            
            $product = $this->productRepository->create($productData);

            if ($productFilePaths) {
                $this->fileRepository->createMultipleForFileable($product, $productFilePaths);
            }

            foreach ($detailsData as $detail) {
                $this->productDetailRepository->createForProduct($product, $detail);
            }

            SyncProductToZohoJob::dispatch($product);

            return $product->load('details', 'files', 'productCategory', 'supplier');
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
            $incomingDetailIds = [];
            if (!empty($detailsData) && is_array($detailsData)) {
                $detailsData = array_values($detailsData);
                foreach ($detailsData as $detail) {
                    if (is_array($detail) && isset($detail['id']) && !empty($detail['id'])) {
                        $incomingDetailIds[] = (int) $detail['id'];
                    }
                }
            }
            $detailsToDelete = array_diff($existingDetailIds, $incomingDetailIds);
            if (!empty($detailsToDelete)) {
                $this->productDetailRepository->deleteByIds($detailsToDelete);
            }
            if (!empty($detailsData) && is_array($detailsData)) {
                foreach ($detailsData as $detail) {
                    if (!is_array($detail)) {
                        continue;
                    }
                    $detailId = isset($detail['id']) ? (int) $detail['id'] : null;
                    if ($detailId && in_array($detailId, $existingDetailIds)) {
                        $this->productDetailRepository->updateForProduct($product, $detailId, $detail);
                    } else {
                        $this->productDetailRepository->createForProduct($product, $detail);
                    }
                }
            }
            SyncProductToZohoJob::dispatch($product);

            return $product->load('details', 'files', 'productCategory', 'supplier');
        });
    }



    public function getPaginatedProducts(?ProductFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->productRepository->paginateProducts($filter, $perPage, $page);
    }

    public function getPublicPaginatedProducts(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return Product::with(['details', 'files', 'productCategory'])
            ->where('status', 'active')
            ->where('max_quantity', '>', 0)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getProductById($id)
    {
        return $this->productRepository->find($id);
    }

    public function getProductBySlug(string $slug): ?Product
    {
        return $this->productRepository->findBySlug($slug);
    }

    public function getProductsForExport(?array $ids = null)
    {
        return $this->productRepository->allForExport($ids);
    }

    public function deleteProductsByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        return DB::transaction(function () use ($ids) {
            $products = Product::with(['details', 'files'])
                ->whereIn('id', $ids)
                ->get();

            foreach ($products as $product) {
                $product->details()->delete();
                $product->files()->delete();
                $this->productRepository->delete($product);
            }

            return $products->count();
        });
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (empty($ids)) {
            return 0;
        }

        return Product::whereIn('id', $ids)->update([
            'status' => $status,
        ]);
    }
}
