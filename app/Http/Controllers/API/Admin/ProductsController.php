<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductsController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function store(StoreProductRequest $request): JsonResponse
    {
        $productData = $request->only([
            'name', 'name_ar',
            'description', 'description_ar',
            'max_quantity',
            'price',
            'currency',
            'main_image',
            'referral_id',
            'discount',
            'discount_type',
            'discount_amount',
            'status',
        ]);

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
            'max_quantity',
            'price',
            'currency',
            'main_image',
            'referral_id',
            'discount',
            'discount_type',
            'discount_amount',
            'status',
        ]);

        $removedFiles = $request->input('removed_files', []);
        $newFiles = $request->input('new_files', []);
        $detailsData = $request->input('details', []);

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
}

