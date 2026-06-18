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
use App\Models\Lead;
use App\Repositories\Interfaces\CartItemRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        protected ProductDiscountTierService $productDiscountTierService,
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
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        ?int $shippingAddressId = null,
        array $shippingAddress = [],
        bool $billingSameAsShipping = false,
        ?int $billingAddressId = null,
        array $billingAddress = [],
        $paymentMethodId = null,
        ?string $giftCardCode = null
    ): Order {
        [$userId, $guestSessionId] = $this->resolveSession($guestSessionId);

        return DB::transaction(function () use (
            $userId,
            $guestSessionId,
            $customerName,
            $customerEmail,
            $customerPhone,
            $shippingAddressId,
            $shippingAddress,
            $billingSameAsShipping,
            $billingAddressId,
            $billingAddress,
            $paymentMethodId,
            $giftCardCode
        ) {
            $items = $this->cartRepository->listBySessionForUpdate($userId, $guestSessionId);

            if ($items->isEmpty()) {
                $this->throwValidation(['cart' => __('validation.cart.empty')], 400);
            }

            $items->loadMissing('product');
            foreach ($items as $item) {
                if (!$item->product) {
                    $this->throwValidation([
                        'cart' => [__('validation.cart.item_product_unavailable')],
                    ]);
                }
            }

            // Lock product rows to prevent concurrent checkout race conditions
            $productIds = $items->pluck('product.id')->filter()->unique()->toArray();
            $lockedProducts = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $user = $userId ? User::query()->with('productDiscountTier')->find($userId) : null;
            $tierPercent = $user
                ? $this->productDiscountTierService->getDiscountForUser($user)
                : 0.0;

            $currency = null;
            $rawSubtotal = 0.0;            // sum(product.price * qty) — pre any discount
            $productFinalSubtotal = 0.0;   // sum(getFinalPrice * qty)  — product-level discount only
            $subtotal = 0.0;               // sum(getFinalPriceForUser * qty) — product + tier

            foreach ($items as $item) {
                // Use the locked product instance for accurate stock check
                $product = $lockedProducts->get($item->product_id) ?? $item->product;

                $available = (int) ($product->max_quantity ?? 0);
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

                $qty = (int) $item->quantity;
                $rawSubtotal += (float) $product->price * $qty;
                $productFinalSubtotal += (float) $product->getFinalPrice() * $qty;
                $subtotal += (float) $product->getFinalPriceForUser($user) * $qty;
            }

            // Decrement stock immediately within this transaction to prevent overselling
            foreach ($items as $item) {
                $product = $lockedProducts->get($item->product_id);
                if ($product) {
                    $product->decrement('max_quantity', (int) $item->quantity);
                }
            }

            $productDiscountPercentage = $tierPercent;
            $productDiscountAmount = round(max(0, $productFinalSubtotal - $subtotal), 2);
            $subtotalBeforeDiscount = round($productFinalSubtotal, 2);

            $vatRate = 0.05;
            $tax = $subtotal * $vatRate;
            $total = $subtotal + $tax;

            if ($user) {
                $shippingName = $shippingAddress['name'] ?? null;
                $shippingPhone = $shippingAddress['mobile'] ?? null;

                $updates = [];
                if ($shippingName && empty($user->name)) {
                    $updates['name'] = $shippingName;
                }
                if ($shippingPhone && empty($user->mobile)) {
                    $existingUserWithPhone = User::where('mobile', $shippingPhone)
                        ->where('id', '!=', $user->id)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($existingUserWithPhone) {
                        $this->throwValidation([
                            'shippingAddress.mobile' => __('validation.custom.mobile.unique'),
                        ], 422);
                    }

                    $updates['mobile'] = $shippingPhone;
                }

                if (!empty($updates)) {
                    $user->update($updates);
                    $user->refresh();
                }
            }

            $order = $this->orderRepository->create([
                'user_id' => $userId,
                'type' => OrderType::Ecommerce,
                'orderable_type' => null,
                'orderable_id' => null,
                'amount' => $total,
                'currency' => $currency ?: 'AED',
                'status' => OrderStatus::PendingPayment,
                'delivery_status' => 'ordered',
                'delivery_status_updated_at' => now(),
                'reference' => $this->makeReference(),
                'meta' => array_filter([
                    'guest_session_id' => $guestSessionId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'product_discount_percentage' => $productDiscountPercentage > 0 ? $productDiscountPercentage : null,
                    'product_discount_amount' => $productDiscountAmount > 0 ? $productDiscountAmount : null,
                ], fn ($v) => $v !== null),
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
                $product = $lockedProducts->get($item->product_id) ?? $item->product;
                $unit = (float) $product->getFinalPriceForUser($user);
                $qty = (int) $item->quantity;
                $lineSubtotal = round($unit * $qty, 2);
                $rawPrice = (float) $product->price;
                $productHadDiscount = $product->hasDiscount();
                $tierApplied = $tierPercent > 0 && $unit < (float) $product->getFinalPrice();
                $anyDiscount = $productHadDiscount || $tierApplied;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'subtotal' => $lineSubtotal,
                    'currency' => $product->currency ?: 'AED',
                    'original_price' => $anyDiscount ? $rawPrice : null,
                    'discount_type' => $productHadDiscount ? $product->discount_type : null,
                    'discount_amount' => $productHadDiscount ? (float) $product->discount_amount : null,
                ]);
            }

            $this->assertCheckoutOrderIntegrity($order, $items->count(), $subtotal, $vatRate, $total, 0.0);

            // Handle gift card if provided
            $giftCardAmountApplied = 0;
            if ($giftCardCode) {
                $purchase = \App\Models\GiftCardPurchase::where('code', $giftCardCode)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($purchase && !$purchase->isExpired() && $purchase->balance > 0) {
                    $giftCardAmountApplied = min((float) $purchase->balance, $total);
                    $newBalance = max(0, (float) $purchase->balance - $giftCardAmountApplied);
                    $purchase->update([
                        'balance' => $newBalance,
                        ...($newBalance <= 0 ? ['status' => 'used'] : []),
                    ]);

                    \App\Models\GiftCardUsage::create([
                        'gift_card_purchase_id' => $purchase->id,
                        'amount_used' => $giftCardAmountApplied,
                        'used_for_type' => 'product',
                        'used_for_id' => $order->id,
                        'used_for_name' => $customerName ?? 'Order #' . $order->reference,
                        'used_for' => 'order',
                        'notes' => 'Applied at online checkout',
                        'verified_by' => $userId,
                    ]);

                    // Update order meta with gift card info
                    $order->update([
                        'meta' => array_merge($order->meta ?? [], [
                            'gift_card_code' => $giftCardCode,
                            'gift_card_amount' => $giftCardAmountApplied,
                        ]),
                    ]);

                    // If gift card fully covers the order, mark as paid immediately.
                    // amount = cash charged (0 here); the gift-card cash was already
                    // recognised as turnover when the card was purchased, so leaving
                    // the full total would double-count it.
                    if ($giftCardAmountApplied >= $total) {
                        $order->update([
                            'status' => \App\Enums\OrderStatus::Paid->value,
                            'paid_at' => now(),
                            'amount' => 0,
                        ]);
                        $this->cartRepository->deleteBySession($userId, $guestSessionId);

                        if (!$userId) {
                            $this->createLeadFromOrder($customerName, $customerEmail, $customerPhone, $shippingAddress);
                        }

                        // Send gift card balance notification
                        if ($purchase->buyer_email) {
                            \Illuminate\Support\Facades\Mail::to($purchase->buyer_email)->queue(new \App\Mail\GiftCardBalanceDeductedMail($purchase, $giftCardAmountApplied));
                        }

                        // Send order confirmation
                        if ($customerEmail) {
                            \Illuminate\Support\Facades\Mail::to($customerEmail)->queue(new \App\Mail\OrderConfirmedMail($order, $customerEmail));
                        }

                        // Upgrade product discount tier after gift-card-paid order
                        if ($userId) {
                            $this->productDiscountTierService->checkAndUpgradeUser($user);
                        }

                        return $order->load(['items.product.files', 'latestPayment', 'shippingAddress.country', 'billingAddress.country']);
                    }

                    // Reduce the order amount for partial gift card coverage
                    $remainingAmount = $total - $giftCardAmountApplied;
                    $order->update(['amount' => $remainingAmount]);

                    // Send gift card balance notification
                    if ($purchase->buyer_email) {
                        \Illuminate\Support\Facades\Mail::to($purchase->buyer_email)->queue(new \App\Mail\GiftCardBalanceDeductedMail($purchase, $giftCardAmountApplied));
                    }
                }
            }

            $meta = $guestSessionId ? ['guest_session_id' => $guestSessionId] : [];
            [$stripeCustomerId, $stripePaymentMethodId] = $this->resolvePaymentMethod($user, $paymentMethodId);
            $this->paymentService->startStripePaymentIntentForOrder(
                $order,
                $customerEmail,
                $meta,
                $stripeCustomerId,
                $stripePaymentMethodId
            );

            $this->cartRepository->deleteBySession($userId, $guestSessionId);

            if (!$userId) {
                $this->createLeadFromOrder($customerName, $customerEmail, $customerPhone, $shippingAddress);
            }

            return $order->load(['items.product.files', 'latestPayment', 'shippingAddress.country', 'billingAddress.country']);
        });
    }

    /**
     * Ensures persisted order items match the cart snapshot and order amount matches VAT-inclusive total.
     */
    protected function assertCheckoutOrderIntegrity(
        Order $order,
        int $expectedItemRows,
        float $expectedSubtotalExVat,
        float $vatRate,
        float $expectedTotalInclVat,
        float $discountAmount = 0.0
    ): void {
        $actualRows = (int) OrderItem::query()->where('order_id', $order->id)->count();
        if ($actualRows !== $expectedItemRows) {
            $this->throwValidation([
                'cart' => [__('validation.cart.checkout_integrity_failed')],
            ], 500);
        }

        $sumLines = (float) OrderItem::query()->where('order_id', $order->id)->sum('subtotal');
        if (!$this->moneyClose($sumLines, $expectedSubtotalExVat)) {
            $this->throwValidation([
                'cart' => [__('validation.cart.checkout_integrity_failed')],
            ], 500);
        }

        $discountedSubtotal = $expectedSubtotalExVat - $discountAmount;
        $computedTotal = $discountedSubtotal + ($discountedSubtotal * $vatRate);
        if (!$this->moneyClose((float) $order->amount, $expectedTotalInclVat)
            || !$this->moneyClose((float) $order->amount, $computedTotal)) {
            $this->throwValidation([
                'cart' => [__('validation.cart.checkout_integrity_failed')],
            ], 500);
        }
    }

    protected function moneyClose(float $a, float $b, float $epsilon = 0.02): bool
    {
        return abs(round($a, 2) - round($b, 2)) <= $epsilon;
    }

    protected function createLeadFromOrder(string $customerName, string $customerEmail, string $customerPhone, array $shippingAddress = []): void
    {
        if (!$customerPhone) {
            return;
        }

        // Don't create lead if a registered user with this phone/email exists
        if (User::where('mobile', $customerPhone)->orWhere('email', $customerEmail)->exists()) {
            return;
        }

        // Don't create duplicate lead
        if (Lead::where('phone', $customerPhone)->exists()) {
            return;
        }

        // Build the best name from shipping address first/last name, fallback to customer name
        $name = $customerName;
        if (!empty($shippingAddress['name']) || !empty($shippingAddress['lastName'])) {
            $firstName = $shippingAddress['name'] ?? '';
            $lastName = $shippingAddress['lastName'] ?? '';
            $fullName = trim("{$firstName} {$lastName}");
            if ($fullName) {
                $name = $fullName;
            }
        }

        Lead::create([
            'name' => $name ?: 'Unknown',
            'phone' => $customerPhone,
            'email' => $customerEmail ?: null,
            'source' => 'order',
            'status' => 'new',
        ]);
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
        // Return max_quantity directly - quantity is now managed by decrementing max_quantity on successful payment
        $maxQuantity = (int) ($product->max_quantity ?? 0);
        return max(0, $maxQuantity);
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
                'country_id' => $address->country_id,
                'zip_code' => $address->zip_code,
            ];
        }

        $required = ['name', 'mobile', 'address', 'city', 'country_id'];
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
            'country_id' => $addressData['country_id'] ?? $addressData['countryId'] ?? null,
            'zip_code' => $addressData['zip_code'] ?? $addressData['zipCode'] ?? null,
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
            'country_id' => $data['country_id'] ?? $data['countryId'] ?? null,
            'zip_code' => $data['zip_code'],
        ]);
    }

    protected function resolvePaymentMethod(?User $user, $paymentMethodId): array
    {
        if (!$paymentMethodId) {
            return [null, null];
        }

        if (is_string($paymentMethodId) && str_starts_with($paymentMethodId, 'pm_')) {
            return [null, $paymentMethodId];
        }

        if (!is_numeric($paymentMethodId)) {
            $this->throwValidation(['paymentMethodId' => __('validation.cart.payment_method_invalid')]);
        }

        $paymentMethodId = (int) $paymentMethodId;

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
            $this->throwValidation([
                'paymentMethodId' => __('validation.cart.saved_payment_method_requires_customer'),
            ]);
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
