<?php

namespace App\Http\Controllers\API\Admin;


use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\AdminCategoryResource;
use App\Http\Resources\AdminServiceResource;
use App\Models\Category;
use App\Models\Service;
use App\Services\ApiResponse;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class CategoriesController
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request,  $filter): JsonResponse
    {
        $perPage = $request->query('per_page', 10);

        $categories = $this->categoryService->getPaginatedCategories($filter, $perPage);

        return ApiResponse::success([
            'categories' => AdminServiceResource::collection($categories),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
            'links' => [
                'first' => $categories->url(1),
                'last' => $categories->url($categories->lastPage()),
                'prev' => $categories->previousPageUrl(),
                'next' => $categories->nextPageUrl(),
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new Category)->getFillable()));
        $category = $this->categoryService->createCategory($data);
        $category->load('services.subServices.items');

        return ApiResponse::success([
            'category' => new AdminCategoryResource($category),
        ], 'Category created successfully.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new Service)->getFillable()));
        $category = $this->categoryService->updateCategory($category, $data);
        $category->load('services.subServices.items');
        return ApiResponse::success([
            'category' => new AdminCategoryResource($category),
        ], 'Category updated successfully.');
    }

//    public function destroy(Category $category): JsonResponse
//    {
//        $this->categoryService->deleteCategory($category);
//
//        return ApiResponse::success([
//            'deleted' => true,
//        ], 'Category deleted successfully.');
//    }
}
