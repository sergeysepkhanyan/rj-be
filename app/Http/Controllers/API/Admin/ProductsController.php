<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteProductsRequest;
use App\Http\Requests\BulkUpdateProductStatusRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Filters\ProductFilter;
use App\Services\ProductExportService;
use App\Services\ProductService;
use App\Services\ProductCategoryService;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected ProductCategoryService $productCategoryService,
        protected ProductExportService $productExportService
    ) {}

    public function index(Request $request, ProductFilter $filter): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $page    = (int) $request->get('page', 1);

        $products = $this->productService->getPaginatedProducts($filter, $perPage, $page);

        return ApiResponse::success([
            'products' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
            ],
            'links' => [
                'first' => $products->url(1),
                'last'  => $products->url($products->lastPage()),
                'prev'  => $products->previousPageUrl(),
                'next'  => $products->nextPageUrl(),
            ],
        ], __('success.products.listed'));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $productData = $request->only([
            'name', 'name_ar',
            'description', 'description_ar',
            'sku_id',
            'product_category_id',
            'supplier_id',
            'max_quantity',
            'reorder_point',
            'price',
            'cost_price',
            'currency',
            'production_date',
            'expiry_date',
            'unit_of_sale',
            'sales_channel',
            'product_type',
            'main_image',
            'referral_id',
            'discount',
            'discount_type',
            'discount_amount',
            'status',
        ]);

        if (empty($productData['product_category_id'])) {
            $name = trim((string) ($request->input('product_category') ?? $request->input('productCategory') ?? ''));
            if ($name !== '') {
                $category = $this->productCategoryService->firstOrCreateByName($name);
                $productData['product_category_id'] = $category->id;
            }
        }

        $productFilePaths = $request->input('images', []);
        $detailsData = $request->input('details', []);

        $productData['main_image'] = $productFilePaths[0] ?? null;

        $product = $this->productService->createProduct(
            $productData,
            $detailsData,
            $productFilePaths
        );

        return ApiResponse::success([
            'product' => new ProductResource($product),
        ], __('success.product.created'));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $productData = $request->only([
            'name', 'name_ar',
            'description', 'description_ar',
            'sku_id',
            'product_category_id',
            'supplier_id',
            'max_quantity',
            'reorder_point',
            'price',
            'cost_price',
            'currency',
            'production_date',
            'expiry_date',
            'unit_of_sale',
            'sales_channel',
            'product_type',
            'main_image',
            'referral_id',
            'discount',
            'discount_type',
            'discount_amount',
            'status',
        ]);

        if (empty($productData['product_category_id'])) {
            $name = trim((string) ($request->input('product_category') ?? $request->input('productCategory') ?? ''));
            if ($name !== '') {
                $category = $this->productCategoryService->firstOrCreateByName($name);
                $productData['product_category_id'] = $category->id;
            }
        }

        $removedFiles = $request->input('removed_files', []);
        $newFiles = $request->input('new_files', []);
        $detailsData = $request->input('details', []);
        
        if (!is_array($removedFiles)) {
            $removedFiles = [];
        } else {
            $removedFiles = array_values(array_filter($removedFiles));
        }
        
        if (!is_array($newFiles)) {
            $newFiles = [];
        } else {
            $newFiles = array_values(array_filter($newFiles));
        }
        
        if (!is_array($detailsData)) {
            $detailsData = [];
        } else {
            $detailsData = array_values(array_filter($detailsData, function($item) {
                return is_array($item);
            }));
        }

        $updated = $this->productService->updateProduct(
            $product->id,
            $productData,
            $detailsData,
            $newFiles,
            $removedFiles
        );

        return ApiResponse::success([
            'product' => new ProductResource($updated),
        ], __('success.product.updated'));
    }

    public function downloadInventory(Request $request)
    {
        $ids = $request->input('ids');

        if (is_string($ids)) {
            $ids = trim($ids);
            if ($ids !== '' && str_starts_with($ids, '[')) {
                $decoded = json_decode($ids, true);
                if (is_array($decoded)) {
                    $ids = $decoded;
                }
            }
            if (is_string($ids)) {
                $ids = array_map('trim', explode(',', $ids));
            }
        }

        if (!is_array($ids)) {
            $ids = null;
        } else {
            $flattened = [];
            foreach ($ids as $value) {
                if (is_string($value) && str_contains($value, ',')) {
                    foreach (array_map('trim', explode(',', $value)) as $part) {
                        $flattened[] = $part;
                    }
                } else {
                    $flattened[] = $value;
                }
            }
            $ids = array_values(array_unique(array_filter(array_map(function ($value) {
                $intVal = (int) $value;
                return $intVal > 0 ? $intVal : null;
            }, $flattened))));
            if (count($ids) === 0) {
                $ids = null;
            }
        }

        return $this->productExportService->streamInventoryCsv($ids);
    }

    public function bulkDelete(BulkDeleteProductsRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || count($ids) === 0) {
            return ApiResponse::success([
                'deleted' => 0,
            ], __('success.product.deleted'));
        }

        $deleted = $this->productService->deleteProductsByIds($ids);

        return ApiResponse::success([
            'deleted' => $deleted,
        ], __('success.product.deleted'));
    }

    public function bulkStatus(BulkUpdateProductStatusRequest $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $status = $request->input('status');

        if (!is_array($ids) || count($ids) === 0) {
            return ApiResponse::success([
                'updated' => 0,
            ], __('success.product.updated'));
        }

        $updated = $this->productService->bulkUpdateStatus($ids, $status);

        return ApiResponse::success([
            'updated' => $updated,
        ], __('success.product.updated'));
    }
}

