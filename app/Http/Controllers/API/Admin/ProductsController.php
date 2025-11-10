<?php

namespace App\Http\Controllers\API\Admin;;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_quantity' => 'nullable|integer',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'main_image' => 'nullable|string',
            'referral_id' => 'nullable|integer',
            'discount' => 'nullable|boolean',
            'discount_type' => 'nullable|string|in:percentage,amount',
            'discount_amount' => 'nullable|numeric',
            'status' => 'nullable|in:active,inactive',

            'images' => 'required|array|min:1',
            'images.*' => 'string',

            'details' => 'nullable|array',
            'details.*.details' => 'required|string',
            'details.*.description' => 'nullable|string',
        ], [
            'images.required' => 'At least one file path is required for the product.',
            'images.min' => 'At least one file path is required for the product.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors(), 'Validation failed', 422);
        }

        $productData = $request->only([
            'name', 'description', 'max_quantity', 'price', 'currency',
            'main_image', 'referral_id', 'discount', 'discount_type',
            'discount_amount', 'status'
        ]);

        $productFilePaths = $request->input('images', []);
        $detailsData = $request->input('details', []);
        $productData['main_image'] = $productFilePaths[0];
        try {
            $product = $this->productService->createProduct($productData, $detailsData, $productFilePaths);
            return ApiResponse::success([
                'product' => new ProductResource($product),
            ], 'Product created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $product = $this->productService->getProductById($id);

        if (!$product) {
            return ApiResponse::error([], 'Product not found', 404);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_quantity' => 'nullable|integer',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'main_image' => 'nullable|string',
            'referral_id' => 'nullable|integer',
            'discount' => 'nullable|boolean',
            'discount_type' => 'nullable|string|in:percentage,amount',
            'discount_amount' => 'nullable|numeric',
            'status' => 'nullable|in:active,inactive',

            'removed_files' => 'nullable|array',
            'removed_files.*' => 'string',

            'new_files' => 'nullable|array',
            'new_files.*' => 'string',

            'details' => 'nullable|array',
            'details.*.id' => 'nullable|integer',
            'details.*.details' => 'required|string',
            'details.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors(), 'Validation failed', 422);
        }

        $productData = $request->only([
            'name', 'description', 'max_quantity', 'price', 'currency',
            'main_image', 'referral_id', 'discount', 'discount_type',
            'discount_amount', 'status'
        ]);

        $removedFiles = $request->input('removed_files', []);
        $newFiles = $request->input('images', []);
        $detailsData = $request->input('details', []);

        try {
            $product = $this->productService->updateProduct(
                $id,
                $productData,
                $detailsData,
                $newFiles,
                $removedFiles
            );

            return ApiResponse::success([
                'product' => new ProductResource($product),
            ], 'Product updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 'Update failed');
        }
    }
}
