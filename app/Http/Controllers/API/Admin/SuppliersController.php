<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuppliersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('name', 'asc')->get();

        return ApiResponse::success([
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|in:active,inactive',
        ]);

        $supplier = Supplier::create($validated);

        return ApiResponse::success([
            'supplier' => $supplier,
        ], 'Supplier created successfully', 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->loadCount('products');

        return ApiResponse::success([
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:suppliers,name,' . $supplier->id,
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|in:active,inactive',
        ]);

        $supplier->update($validated);

        return ApiResponse::success([
            'supplier' => $supplier,
        ], 'Supplier updated successfully');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        // Check if supplier has products
        if ($supplier->products()->exists()) {
            return ApiResponse::error(
                'Cannot delete supplier with associated products. Please reassign or delete products first.',
                400
            );
        }

        $supplier->delete();

        return ApiResponse::success([], 'Supplier deleted successfully');
    }

    public function dropdown(): JsonResponse
    {
        $suppliers = Supplier::active()
            ->select('id', 'name')
            ->orderBy('name', 'asc')
            ->get();

        return ApiResponse::success([
            'suppliers' => $suppliers,
        ]);
    }
}
