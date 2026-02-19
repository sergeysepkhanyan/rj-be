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
        $gender = $request->query('gender');

        if ($gender) {
            $categories = $this->categoryService->getByGender($gender);
        } else {
            $filters = [
                'id'   => $request->query('id'),
                'name' => $request->query('name'),
            ];
            $categories = $this->categoryService->getAllCategories($filters);
        }

        return ApiResponse::success([
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategoryById($id);

        return ApiResponse::success([
            'category' => new CategoryResource($category),
        ]);
    }
}
