<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Wishlist;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $wishlistItems = $user->wishlistProducts()
            ->with(['files', 'details', 'productCategory'])
            ->orderByPivot('created_at', 'desc')
            ->get();

        return ApiResponse::success([
            'items' => ProductResource::collection($wishlistItems),
            'count' => $wishlistItems->count(),
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $user = auth()->user();
        $productId = $request->product_id;

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            $count = Wishlist::where('user_id', $user->id)->count();
            return ApiResponse::success([
                'added' => false,
                'count' => $count,
            ], 'Product removed from wishlist');
        }

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);

        $count = Wishlist::where('user_id', $user->id)->count();

        return ApiResponse::success([
            'added' => true,
            'count' => $count,
        ], 'Product added to wishlist');
    }

    public function ids(): JsonResponse
    {
        $user = auth()->user();

        $productIds = Wishlist::where('user_id', $user->id)
            ->pluck('product_id')
            ->toArray();

        return ApiResponse::success([
            'productIds' => $productIds,
            'count' => count($productIds),
        ]);
    }

    public function destroy(int $productId): JsonResponse
    {
        $user = auth()->user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return ApiResponse::error(null, 'Product not in wishlist', 404);
        }

        $count = Wishlist::where('user_id', $user->id)->count();

        return ApiResponse::success([
            'count' => $count,
        ], 'Product removed from wishlist');
    }
}
