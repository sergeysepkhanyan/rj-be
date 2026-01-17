<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryResource;
use App\Services\ApiResponse;
use App\Services\ProductCategoryService;
use Illuminate\Http\JsonResponse;

class ProductCategoriesController extends Controller
{
    public function __construct(
        protected ProductCategoryService $productCategoryService
    ) {}

    public function index(): JsonResponse
    {
        $categories = $this->productCategoryService->list();

        return ApiResponse::success([
            'categories' => ProductCategoryResource::collection($categories),
        ]);
    }
}
