<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::withCount('products');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        $categories = $query->ordered()->get();

        return ApiResponse::success([
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_categories,name',
            'name_ar' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        // Set default sort_order to be at the end
        if (!isset($validated['sort_order'])) {
            $maxOrder = ProductCategory::max('sort_order') ?? 0;
            $validated['sort_order'] = $maxOrder + 1;
        }

        $category = ProductCategory::create($validated);

        return ApiResponse::success([
            'category' => $category,
        ], 'Category created successfully', 201);
    }

    public function show(ProductCategory $productCategory): JsonResponse
    {
        $productCategory->loadCount('products');

        return ApiResponse::success([
            'category' => $productCategory,
        ]);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:product_categories,name,' . $productCategory->id,
            'name_ar' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive',
        ]);

        $productCategory->update($validated);

        return ApiResponse::success([
            'category' => $productCategory,
        ], 'Category updated successfully');
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        // Check if category has products
        if ($productCategory->products()->exists()) {
            return ApiResponse::error(
                'Cannot delete category with associated products. Please reassign or delete products first.',
                400
            );
        }

        $productCategory->delete();

        return ApiResponse::success([], 'Category deleted successfully');
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:product_categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['categories'] as $item) {
            ProductCategory::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return ApiResponse::success([], 'Categories reordered successfully');
    }

    public function dropdown(): JsonResponse
    {
        $categories = ProductCategory::active()
            ->ordered()
            ->select('id', 'name', 'name_ar')
            ->get();

        return ApiResponse::success([
            'categories' => $categories,
        ]);
    }
}
