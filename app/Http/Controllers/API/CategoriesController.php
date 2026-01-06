<?php

namespace App\Http\Controllers\API;


use App\Http\Resources\CategoryResource;
use App\Services\ApiResponse;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class CategoriesController
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getAllCategories();

        return ApiResponse::success([
            'categories' => CategoryResource::collection($categories),
        ]);
    }
}
