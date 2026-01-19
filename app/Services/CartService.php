<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Interfaces\CartItemRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class CartService
{
    public function __construct(
        protected CartItemRepositoryInterface $cartRepository,
        protected OrderRepositoryInterface $orderRepository,
        protected PaymentService $paymentService,
    ) {}

    public function listCart(?string $guestSessionId = null): Collection
    {
        [$userId, $guestSessionId] = $this->resolveSession($guestSessionId);

        return $this->cartRepository->listBySession($userId, $guestSessionId);
    }

    public function addToCart(int $productId, int $quantity = 1, ?string $guestSessionId = null): CartItem
    {
        [$userId, $guestSessionId] = $this->resolveSession($guestSessionId);

        $product = Product::findOrFail($productId);
        $quantity = max(1, $quantity);

        $existing = $this->cartRepository->findBySessionProduct($userId, $guestSessionId, $productId);
        $newQty = ($existing?->quantity ?? 0) + $quantity;

        $available = $this->getAvailableQuantity($product, $userId, $guestSessionId, $existing?->id);
        if ($newQty > $available) {
            $this->throwValidation([
                'quantity' => __("validation.insufficient_stock", ['available' => $available]),
            ]);
        }

        if ($existing) {
            return $this->cartRepository->update($existing, ['quantity' => $newQty]);
        }

        return $this->cartRepository->create([
            'user_id' => $userId,
            'guest_session_id' => $guestSessionId,
            'product_id' => $productId,
            'quantity' => $newQty,
        ]);
    }

    public function updateCartItem(int $productId, int $quantity, ?string $guestSessionId = null): ?CartItem
    {
        [$userId, $guestSessionId] = $this->resolveSession($guestSessionId);

        $item = $this->cartRepository->findBySessionProduct($userId, $guestSessionId, $productId);
        if (!$item) {
            return null;
        }

        if ($quantity <= 0) {
            $this->cartRepository->delete($item);
            return null;
        }

        $product = Product::findOrFail($productId);
        $available = $this->getAvailableQuantity($product, $userId, $guestSessionId, $item->id);
        if ($quantity > $available) {
            $this->throwValidation([
                'quantity' => __("validation.insufficient_stock", ['available' => $available]),
            ]);
        }

        return $this->cartRepository->update($item, ['quantity' => $quantity]);
    }

    public function removeFromCart(int $productId, ?string $guestSessionId = null): bool
    {
        [$userId, $guestSessionId] = $this->resolveSession($guestSessionId);

        $item = $this->cartRepository->findBySessionProduct($userId, $guestSessionId, $productId);
        if (!$item) {
            return false;
        }

        $this->cartRepository->delete($item);
        return true;
    }

    public function clearCart(?string $guestSessionId = null): int
    {
        [$userId, $guestSessionId] = $this->resolveSession($guestSessionId);

        return $this->cartRepository->deleteBySession($userId, $guestSessionId);
    }

    public function mergeGuestCartToUser(string $guestSessionId, int $userId): void
    {
        $guestItems = $this->cartRepository->listBySession(null, $guestSessionId);
        if ($guestItems->isEmpty()) {
            return;
        }

        foreach ($guestItems as $guestItem) {
            $product = $guestItem->product;
            if (!$product) {
                continue;
            }

            $existing = $this->cartRepository->findBySessionProduct($userId, null, $product->id);
            $existingQty = $existing?->quantity ?? 0;
            $newQty = $existingQty + (int) $guestItem->quantity;

            $available = $this->getAvailableQuantity($product, $userId, null, $existing?->id);
            if ($available <= 0) {
                $this->cartRepository->delete($guestItem);
                continue;
            }

            $finalQty = min($newQty, $available);

            if ($existing) {
                $this->cartRepository->update($existing, ['quantity' => $finalQty]);
                $this->cartRepository->delete($guestItem);
                continue;
            }

            $this->cartRepository->update($guestItem, [
                'user_id' => $userId,
                'guest_session_id' => null,
                'quantity' => $finalQty,
            ]);
        }
    }

    public function checkout(
        ?string $guestSessionId,
        ?string $customerEmail = null,
        ?int $shippingAddressId = null,
        array $shippingAddress = [],
        bool $billingSameAsShipping = false,
        ?int $billingAddressId = null,
        array $billingAddress = [],
        ?int $paymentMethodId = null
    ): Order
    {
        [$userId, $guestSessionId] = $this->resolveSession($guestSessionId);
        $items = $this->cartRepository->listBySession($userId, $guestSessionId);

        if ($items->isEmpty()) {
            $this->throwValidation(['cart' => __('validation.cart.empty')], 400);
        }

        if (!$userId && !$customerEmail) {
            $this->throwValidation(['customerEmail' => __('validation.cart.customer_email_required')]);
        }

        $currency = null;
        $total = 0.0;

        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }

            $available = $this->getAvailableQuantity($product, $userId, $guestSessionId, $item->id);
            if ($item->quantity > $available) {
                $this->throwValidation([
                    'quantity' => __("validation.insufficient_stock", ['available' => $available]),
                ]);
            }

            $itemCurrency = $product->currency ?: 'AED';
            if ($currency && $currency !== $itemCurrency) {
                $this->throwValidation(['currency' => __('validation.cart.mixed_currency')]);
            }
            $currency = $itemCurrency;

            $total += ((float) $product->price) * (int) $item->quantity;
        }

        $user = $userId ? User::find($userId) : null;
        $email = $customerEmail ?: $user?->email;

        $order = $this->orderRepository->create([
            'user_id' => $userId,
            'type' => OrderType::Ecommerce,
            'orderable_type' => null,
            'orderable_id' => null,
            'amount' => $total,
            'currency' => $currency ?: 'AED',
            'status' => OrderStatus::PendingPayment,
            'reference' => $this->makeReference(),
            'meta' => [
                'guest_session_id' => $guestSessionId,
                'customer_email' => $email,
            ],
        ]);

        $this->attachOrderAddresses(
            $order,
            $userId,
            $shippingAddressId,
            $shippingAddress,
            $billingSameAsShipping,
            $billingAddressId,
            $billingAddress
        );

        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }
            $unit = (float) $product->price;
            $subtotal = $unit * (int) $item->quantity;
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => (int) $item->quantity,
                'unit_price' => $unit,
                'subtotal' => $subtotal,
                'currency' => $product->currency ?: 'AED',
            ]);
        }

        $meta = $guestSessionId ? ['guest_session_id' => $guestSessionId] : [];
        [$stripeCustomerId, $stripePaymentMethodId] = $this->resolveSavedPaymentMethod($user, $paymentMethodId);
        $this->paymentService->startStripePaymentIntentForOrder(
            $order,
            $email,
            $meta,
            $stripeCustomerId,
            $stripePaymentMethodId
        );

        $this->cartRepository->deleteBySession($userId, $guestSessionId);

        return $order->load(['latestPayment', 'shippingAddress', 'billingAddress']);
    }

    protected function resolveSession(?string $guestSessionId): array
    {
        $userId = auth()->user()?->id;
        if (!$userId && JWTAuth::getToken()) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
                $userId = $user?->id;
            } catch (TokenExpiredException|TokenInvalidException|JWTException $e) {
                throw new HttpResponseException(
                    ApiResponse::error(
                        ['auth' => [__('auth.unauthorized')]],
                        __('auth.unauthorized'),
                        401
                    )
                );
            }
        }
        if ($userId) {
            if ($guestSessionId) {
                $this->mergeGuestCartToUser($guestSessionId, $userId);
            }
            return [$userId, null];
        }

        if (!$guestSessionId) {
            $this->throwValidation(['guestSessionId' => __('validation.cart.guest_session_required')]);
        }

        return [null, $guestSessionId];
    }

    protected function getAvailableQuantity(Product $product, ?int $userId, ?string $guestSessionId, ?int $excludeCartItemId = null): int
    {
        $base = (int) ($product->max_quantity ?? 0);
        if ($base <= 0) {
            return 0;
        }

        $orderedQty = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', function ($q) {
                $q->whereIn('status', [
                    OrderStatus::Pending->value,
                    OrderStatus::Paid->value,
                    OrderStatus::Fulfilled->value,
                ]);
            })
            ->sum('quantity');

        $available = max(0, $base - (int) $orderedQty);

        if ($excludeCartItemId) {
            return $available;
        }

        if ($userId || $guestSessionId) {
            $currentCartQty = CartItem::query()
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when(!$userId && $guestSessionId, fn ($q) => $q->where('guest_session_id', $guestSessionId))
                ->where('product_id', $product->id)
                ->sum('quantity');

            $available = max(0, $available - (int) $currentCartQty);
        }

        return $available;
    }

    protected function makeReference(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }

    protected function attachOrderAddresses(
        Order $order,
        ?int $userId,
        ?int $shippingAddressId,
        array $shippingAddress,
        bool $billingSameAsShipping,
        ?int $billingAddressId,
        array $billingAddress
    ): void {
        $shipping = $this->resolveAddressPayload($userId, $shippingAddressId, $shippingAddress, 'shipping');
        $this->createOrderAddress($order, $userId, $shipping, 'shipping');

        if ($billingSameAsShipping) {
            $this->createOrderAddress($order, $userId, $shipping, 'billing');
            return;
        }

        $billing = $this->resolveAddressPayload($userId, $billingAddressId, $billingAddress, 'billing');
        $this->createOrderAddress($order, $userId, $billing, 'billing');
    }

    protected function resolveAddressPayload(
        ?int $userId,
        ?int $addressId,
        array $addressData,
        string $type
    ): array {
        if ($userId && $addressId) {
            $address = Address::query()
                ->where('id', $addressId)
                ->where('user_id', $userId)
                ->whereNull('order_id')
                ->first();

            if (!$address) {
                $this->throwValidation(['addressId' => __('validation.cart.address_id_required')]);
            }

            return [
                'name' => $address->name,
                'last_name' => $address->last_name,
                'mobile' => $address->mobile,
                'address' => $address->address,
                'additional_address' => $address->additional_address,
                'city' => $address->city,
                'state' => $address->state,
                'zip_code' => $address->zip_code,
            ];
        }

        $required = ['name', 'mobile', 'address', 'city', 'state', 'zip_code'];
        foreach ($required as $field) {
            if (empty($addressData[$field])) {
                $this->throwValidation(['address' => __('validation.cart.address_required')]);
            }
        }

        return [
            'name' => $addressData['name'],
            'last_name' => $addressData['last_name'] ?? null,
            'mobile' => $addressData['mobile'],
            'address' => $addressData['address'],
            'additional_address' => $addressData['additional_address'] ?? null,
            'city' => $addressData['city'],
            'state' => $addressData['state'],
            'zip_code' => $addressData['zip_code'],
        ];
    }

    protected function createOrderAddress(Order $order, ?int $userId, array $data, string $type): void
    {
        Address::create([
            'user_id' => $userId,
            'order_id' => $order->id,
            'type' => $type,
            'is_default' => false,
            'name' => $data['name'],
            'last_name' => $data['last_name'] ?? null,
            'mobile' => $data['mobile'],
            'address' => $data['address'],
            'additional_address' => $data['additional_address'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'],
            'zip_code' => $data['zip_code'],
        ]);
    }

    protected function resolveSavedPaymentMethod(?User $user, ?int $paymentMethodId): array
    {
        if (!$paymentMethodId) {
            return [null, null];
        }

        if (!$user) {
            $this->throwValidation(['paymentMethodId' => __('validation.cart.payment_method_required')]);
        }

        $method = PaymentMethod::query()
            ->where('id', $paymentMethodId)
            ->where('user_id', $user->id)
            ->first();

        if (!$method || $method->provider !== 'stripe') {
            $this->throwValidation(['paymentMethodId' => __('validation.cart.payment_method_invalid')]);
        }

        if (!$user->stripe_customer_id) {
            $this->throwValidation(['paymentMethodId' => __('validation.cart.payment_method_invalid')]);
        }

        return [$user->stripe_customer_id, $method->token];
    }

    protected function throwValidation(array $errors, int $status = 422): void
    {
        throw new HttpResponseException(
            ApiResponse::error($errors, __('validation.failed'), $status)
        );
    }
}
