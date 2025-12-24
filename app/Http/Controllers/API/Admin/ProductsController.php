<?php

namespace App\Http\Controllers\API\Admin;;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ProductService;
use App\Services\ApiResponse;

class ProductsController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

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

        $product = $this->productService->createProduct($productData, $detailsData, $productFilePaths);

        return ApiResponse::success([
            'product' => new ProductResource($product),
        ], 'Product created successfully');
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
        ], 'Product updated successfully');
    }
}
