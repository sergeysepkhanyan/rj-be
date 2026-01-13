<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Models\Category;
use App\Services\ApiResponse;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriesController
{
    public function __construct(protected CategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getByGender($request->get('gender') ?? '');

        return ApiResponse::success([
            'categories' => AdminCategoryResource::collection($categories),
        ], __('success.category.list'));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new Category)->getFillable()));

        $category = $this->categoryService->createCategory($data);
        $category->load('services.subServices.items');

        return ApiResponse::success([
            'category' => new AdminCategoryResource($category),
        ], __('success.category.created'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->all();

        $data = array_intersect_key($data, array_flip((new Category)->getFillable()));

        $category = $this->categoryService->updateCategory($category, $data);
        $category->load('services.subServices.items');

        return ApiResponse::success([
            'category' => new AdminCategoryResource($category),
        ], __('success.category.updated'));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->deleteCategory($category);

        return ApiResponse::success([
            'deleted' => true,
        ], __('success.category.deleted'));

    }
}
