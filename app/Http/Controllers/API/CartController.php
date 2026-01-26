<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\CheckoutCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Product;
use App\Services\ApiResponse;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $guestSessionId = $this->getGuestSessionId($request);
        $items = $this->cartService->listCart($guestSessionId);

        return ApiResponse::success([
            'items' => CartItemResource::collection($items),
        ]);
    }

    public function store(AddToCartRequest $request): JsonResponse
    {
        $productId = (int) $request->input('product_id');
        $quantity = (int) $request->input('quantity', 1);
        $guestSessionId = $this->getGuestSessionId($request);

        $item = $this->cartService->addToCart($productId, $quantity, $guestSessionId);

        return ApiResponse::success([
            'item' => new CartItemResource($item->load('product')),
        ]);
    }

    public function update(UpdateCartItemRequest $request, Product $product): JsonResponse
    {
        $quantity = (int) $request->input('quantity');
        $guestSessionId = $this->getGuestSessionId($request);

        $item = $this->cartService->updateCartItem($product->id, $quantity, $guestSessionId);

        return ApiResponse::success([
            'item' => $item ? new CartItemResource($item->load('product')) : null,
        ]);
    }

    public function increment(Request $request, Product $product): JsonResponse
    {
        $guestSessionId = $this->getGuestSessionId($request);
        $item = $this->cartService->addToCart($product->id, 1, $guestSessionId);

        return ApiResponse::success([
            'item' => new CartItemResource($item->load('product')),
        ]);
    }

    public function decrement(Request $request, Product $product): JsonResponse
    {
        $guestSessionId = $this->getGuestSessionId($request);
        $item = $this->cartService->updateCartItem($product->id, $this->getCurrentQty($product->id, $guestSessionId) - 1, $guestSessionId);

        return ApiResponse::success([
            'item' => $item ? new CartItemResource($item->load('product')) : null,
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $guestSessionId = $this->getGuestSessionId($request);
        $removed = $this->cartService->removeFromCart($product->id, $guestSessionId);

        return ApiResponse::success([
            'removed' => $removed,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $guestSessionId = $this->getGuestSessionId($request);
        $deleted = $this->cartService->clearCart($guestSessionId);

        return ApiResponse::success([
            'deleted' => $deleted,
        ]);
    }

    public function checkout(CheckoutCartRequest $request): JsonResponse
    {
        $guestSessionId = $this->getGuestSessionId($request);
        $customerName = $request->input('customer_name');
        $customerEmail = $request->input('customer_email');
        $customerPhone = $request->input('customer_phone');
        $shippingAddressId = $request->input('shipping_address_id');
        $billingAddressId = $request->input('billing_address_id');
        $billingSameAsShipping = (bool) $request->input('billing_same_as_shipping', false);
        $shippingAddress = $request->input('shipping_address', []);
        $billingAddress = $request->input('billing_address', []);
        $paymentMethodId = $request->input('payment_method_id');
        $paymentMethodToken = $request->input('payment_method_token');

        $paymentMethod = $paymentMethodToken ?: $paymentMethodId;

        $order = $this->cartService->checkout(
            $guestSessionId,
            $customerName,
            $customerEmail,
            $customerPhone,
            $shippingAddressId,
            $shippingAddress,
            $billingSameAsShipping,
            $billingAddressId,
            $billingAddress,
            $paymentMethod
        );

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ]);
    }

    private function getGuestSessionId(Request $request): ?string
    {
        return $request->input('guest_session_id')
            ?? $request->input('guestSessionId')
            ?? $request->header('X-Guest-Session-Id')
            ?? $request->header('X-Guest-Session')
            ?? $request->cookie('guest_session_id');
    }

    private function getCurrentQty(int $productId, ?string $guestSessionId): int
    {
        $items = $this->cartService->listCart($guestSessionId);
        $item = $items->firstWhere('product_id', $productId);
        return (int) ($item?->quantity ?? 0);
    }
}
