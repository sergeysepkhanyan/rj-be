<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filters\OrderFilter;
use App\Mail\GiftCardBalanceDeductedMail;
use App\Mail\NewOrderAdminNotificationMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderDeliveryStatusUpdatedMail;
use App\Mail\OrderRefundedMail;
use App\Models\User;
use App\Models\Address;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\ApiResponse;
use App\Services\PaymentService;
use App\Support\OrderStateMachine;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected BookingRepositoryInterface $bookingRepository,
        protected PaymentService $paymentService,
        protected AddressRepositoryInterface $addressRepository,
        protected GiftCardService $giftCardService
    ) {}

    public function createManually(array $data, bool $sendEmail = false): Order
    {
        return DB::transaction(function () use ($data, $sendEmail) {
            $this->assertManualOrderPayloadConsistent($data);

            $customerName = $data['customer_name'];
            $customerEmail = $data['customer_email'];
            $customerPhone = $data['customer_phone'];
            $items = $data['items'];
            $total = (float) $data['total'];
            $currency = $data['currency'] ?? 'AED';
            $shippingAddress = $data['shipping_address'];
            $billingAddress = $data['billing_address'] ?? null;
            $billingSameAsShipping = (bool) ($data['billing_same_as_shipping'] ?? false);

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => [__('validation.product_not_found', ['id' => $item['product_id']])]
                    ]);
                }

                $available = (int) $product->max_quantity;
                if ($item['quantity'] > $available) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => [__('validation.insufficient_stock', ['available' => $available])]
                    ]);
                }
            }

            $order = $this->orderRepository->create([
                'user_id' => null,
                'type' => OrderType::Ecommerce,
                'orderable_type' => null,
                'orderable_id' => null,
                'amount' => $total,
                'currency' => $currency,
                'status' => OrderStatus::Pending->value,
                'delivery_status' => 'ordered',
                'delivery_status_updated_at' => now(),
                'reference' => $this->makeReference(),
                'meta' => [
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'discount_type' => $data['discount_type'] ?? null,
                    'discount_value' => $data['discount_value'] ?? null,
                    'discount_label' => $data['discount_label'] ?? null,
                    'discount_amount' => $data['discount_amount'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            $this->createOrderAddress($order, null, $shippingAddress, 'shipping');

            if ($billingSameAsShipping) {
                $this->createOrderAddress($order, null, $shippingAddress, 'billing');
            } elseif ($billingAddress) {
                $this->createOrderAddress($order, null, $billingAddress, 'billing');
            }

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                $unitPrice = (float) $item['price'];
                $quantity = (int) $item['quantity'];
                $subtotal = $unitPrice * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'currency' => $currency,
                    'image' => $item['image'] ?? null,
                ]);

                $product->decrement('max_quantity', $quantity);
            }

            $this->assertPersistedLineItemsMatchManualOrder($order, $items, (float) $data['subtotal']);

            if ($sendEmail) {
                $this->sendOrderConfirmation($order);
            }

            return $order->load(['items.product', 'shippingAddress.country', 'billingAddress.country']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function assertManualOrderPayloadConsistent(array $data): void
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) < 1) {
            throw ValidationException::withMessages([
                'items' => [__('validation.order.items_required')],
            ]);
        }

        foreach ($items as $i => $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty < 1) {
                throw ValidationException::withMessages([
                    "items.{$i}.quantity" => [__('validation.order.invalid_line_quantity')],
                ]);
            }
        }

        $linesSubtotal = 0.0;
        foreach ($items as $item) {
            $linesSubtotal += (float) $item['price'] * (int) $item['quantity'];
        }

        $declaredSubtotal = (float) $data['subtotal'];
        $tax = (float) ($data['tax'] ?? 0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        $declaredTotal = (float) $data['total'];

        if (!$this->amountsClose($linesSubtotal, $declaredSubtotal)) {
            throw ValidationException::withMessages([
                'subtotal' => [__('validation.order.subtotal_lines_mismatch')],
            ]);
        }

        $giftCardAmount = (float) ($data['gift_card_amount'] ?? 0);
        $expectedTotal = $declaredSubtotal + $tax - $discountAmount - $giftCardAmount;
        if (!$this->amountsClose(max(0, $expectedTotal), $declaredTotal)) {
            throw ValidationException::withMessages([
                'total' => [__('validation.order.total_breakdown_mismatch')],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function assertPersistedLineItemsMatchManualOrder(Order $order, array $items, float $declaredSubtotal): void
    {
        if ($order->items()->count() !== count($items)) {
            throw ValidationException::withMessages([
                'items' => [__('validation.order.line_persistence_mismatch')],
            ]);
        }

        $sumLines = (float) OrderItem::query()->where('order_id', $order->id)->sum('subtotal');
        if (!$this->amountsClose($sumLines, $declaredSubtotal)) {
            throw ValidationException::withMessages([
                'items' => [__('validation.order.line_persistence_mismatch')],
            ]);
        }
    }

    protected function amountsClose(float $a, float $b, float $epsilon = 0.02): bool
    {
        return abs(round($a, 2) - round($b, 2)) <= $epsilon;
    }

    protected function createOrderAddress(Order $order, ?int $userId, array $data, string $type): void
    {
        $this->addressRepository->create([
            'user_id' => $userId,
            'order_id' => $order->id,
            'type' => $type,
            'is_default' => false,
            'name' => $data['name'],
            'last_name' => $data['last_name'] ?? $data['lastName'] ?? null,
            'mobile' => $data['mobile'],
            'address' => $data['address'],
            'additional_address' => $data['additional_address'] ?? $data['additionalAddress'] ?? null,
            'city' => $data['city'],
            'country_id' => $data['country_id'] ?? $data['countryId'] ?? null,
            'zip_code' => $data['zip_code'] ?? $data['zipCode'] ?? null,
        ]);
    }

    public function createInStore(array $data, bool $sendEmail = false): Order
    {
        return DB::transaction(function () use ($data, $sendEmail) {
            $this->assertManualOrderPayloadConsistent($data);

            $customerName = $data['customer_name'];
            $customerEmail = $data['customer_email'] ?? null;
            $customerPhone = $data['customer_phone'] ?? null;
            $clientUserId = $data['client_user_id'] ?? null;
            $items = $data['items'];
            $total = (float) $data['total'];
            $currency = $data['currency'] ?? 'AED';
            $paymentMethod = $data['payment_method'] ?? 'cash';

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => [__('validation.product_not_found', ['id' => $item['product_id']])]
                    ]);
                }

                $available = (int) $product->max_quantity;
                if ($item['quantity'] > $available) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => [__('validation.insufficient_stock', ['available' => $available])]
                    ]);
                }
            }

            $order = $this->orderRepository->create([
                'user_id' => $clientUserId,
                'type' => OrderType::Ecommerce,
                'orderable_type' => null,
                'orderable_id' => null,
                'amount' => $total,
                'currency' => $currency,
                'status' => OrderStatus::Paid->value, // In-store orders are paid immediately
                'paid_at' => now(),
                'delivery_status' => 'delivered', // In-store = delivered immediately
                'delivery_status_updated_at' => now(),
                'reference' => $this->makeReference(),
                'meta' => [
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'payment_method' => $paymentMethod,
                    'order_type' => 'in_store',
                    'discount_type' => $data['discount_type'] ?? null,
                    'discount_value' => $data['discount_value'] ?? null,
                    'discount_label' => $data['discount_label'] ?? null,
                    'discount_amount' => $data['discount_amount'] ?? null,
                    'gift_card_code' => $data['gift_card_code'] ?? null,
                    'gift_card_amount' => $data['gift_card_amount'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
            ]);

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                $unitPrice = (float) $item['price'];
                $quantity = (int) $item['quantity'];
                $subtotal = $unitPrice * $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'currency' => $currency,
                    'image' => $item['image'] ?? null,
                ]);

                // Decrease stock immediately
                $product->decrement('max_quantity', $quantity);
            }

            $this->assertPersistedLineItemsMatchManualOrder($order, $items, (float) $data['subtotal']);

            // Deduct from gift card if used
            $giftCardCode = $data['gift_card_code'] ?? null;
            $giftCardAmount = (float) ($data['gift_card_amount'] ?? 0);
            if ($giftCardCode && $giftCardAmount > 0) {
                $purchase = \App\Models\GiftCardPurchase::where('code', $giftCardCode)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();
                if ($purchase && $purchase->balance > 0) {
                    $amountToDeduct = min($giftCardAmount, (float) $purchase->balance);
                    $newBalance = max(0, (float) $purchase->balance - $amountToDeduct);
                    $purchase->update([
                        'balance' => $newBalance,
                        ...($newBalance <= 0 ? ['status' => 'used'] : []),
                    ]);
                    \App\Models\GiftCardUsage::create([
                        'gift_card_purchase_id' => $purchase->id,
                        'amount_used' => $amountToDeduct,
                        'used_for_type' => 'product',
                        'used_for_id' => $order->id,
                        'used_for_name' => $customerName ?? 'In-Store Order #' . $order->reference,
                        'used_for' => 'order',
                        'notes' => 'Applied via in-store sale',
                        'verified_by' => auth()->id(),
                    ]);

                    // Notify gift card buyer about balance deduction
                    if ($purchase->buyer_email) {
                        Mail::to($purchase->buyer_email)->queue(new GiftCardBalanceDeductedMail($purchase, $amountToDeduct));
                    }
                }
            }

            if ($sendEmail && $customerEmail) {
                $this->sendOrderConfirmation($order);
            }

            // Upgrade product discount tier for the client
            if ($clientUserId) {
                $clientUser = \App\Models\User::find($clientUserId);
                if ($clientUser) {
                    app(\App\Services\ProductDiscountTierService::class)->checkAndUpgradeUser($clientUser);
                }
            }

            return $order->load(['items.product']);
        });
    }

    public function createForBooking(Booking $booking, string $paymentMode): Order
    {
        $existing = $this->orderRepository->findByOrderable(Booking::class, $booking->id);
        if ($existing) {
            return $existing;
        }

        $status = ($paymentMode === 'pay_now')
            ? OrderStatus::PendingPayment->value
            : OrderStatus::Pending->value;

        return $this->orderRepository->create([
            'user_id' => $booking->user_id,
            'type' => OrderType::Booking,
            'orderable_type' => Booking::class,
            'orderable_id'   => $booking->id,
            'amount'   => $booking->final_price ?? $booking->price,
            'currency' => 'AED',
            'status' => $status,
            'reference' => $this->makeReference(),
            'meta' => [
                'booking_id' => $booking->id,
            ],
        ]);
    }

    public function markPaid(Order $order, array $meta = []): Order
    {
        $currentStatus = $order->status;

        // Idempotent: already paid, just return — but backfill paid_at if a
        // prior code path set status='paid' without it. A NULL paid_at hides
        // the order from getTodaysTurnover() since that query filters by
        // whereDate('paid_at', $date).
        if ($currentStatus === OrderStatus::Paid->value) {
            if ($order->paid_at === null) {
                $payment = $order->latestPayment;
                $backfillPaidAt = $payment?->paid_at ?? $order->updated_at ?? now();
                $order->update(['paid_at' => $backfillPaidAt]);
                $order->refresh();
            }
            return $order;
        }

        // Validate status transition
        OrderStateMachine::assertTransition($currentStatus, OrderStatus::Paid->value);

        $updateData = [
            'status' => OrderStatus::Paid->value,
            'meta'   => array_merge($order->meta ?? [], $meta),
            'paid_at' => now(),
        ];

        $order = $this->orderRepository->update($order, $updateData);
        $order->refresh();

        $statusUpdateSuccess = $order->status === OrderStatus::Paid->value;
        if (!$statusUpdateSuccess) {
            DB::table('orders')
                ->where('id', $order->id)
                ->update([
                    'status' => OrderStatus::Paid->value,
                    'paid_at' => now(),
                ]);
            $order->refresh();
        }

        // Stock is now decremented at checkout time (CartService),
        // so no need to decrement again here for ecommerce orders.
        // Manual/in-store orders decrement at creation time.

        return $order;
    }

    public function cancel(Order $order, array $meta = []): Order
    {
        $currentStatus = $order->status;

        // Idempotent
        if ($currentStatus === OrderStatus::Canceled->value) {
            return $order;
        }

        OrderStateMachine::assertTransition($currentStatus, OrderStatus::Canceled->value);

        // Restore stock for ecommerce orders (stock was decremented at checkout)
        if ($order->getTypeValue() === OrderType::Ecommerce->value) {
            $this->increaseProductQuantities($order);
        }

        $this->giftCardService->reverseForOrder($order);

        $order = $this->orderRepository->update($order, [
            'status' => OrderStatus::Canceled->value,
            'cancelled_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);

        // Re-evaluate the product discount tier now that spend has decreased.
        if ($order->getTypeValue() === OrderType::Ecommerce->value && $order->user_id) {
            app(ProductDiscountTierService::class)->checkAndUpgradeUser($order->user);
        }

        return $order;
    }

    public function cancelBookingForOrder(Order $order): void
    {
        if (!$order->orderable || $order->getTypeValue() !== 'booking') {
            return;
        }
        $booking = $order->orderable;
        if ($booking instanceof Booking) {
            $update = ['status' => 'cancelled'];
            // Only clear payment status for bookings that were never paid; never
            // overwrite a genuinely paid/refunded booking from a late failure event.
            if (!in_array($booking->payment_status, ['paid', 'refunded'], true)) {
                $update['payment_status'] = 'unpaid';
            }
            $this->bookingRepository->update($booking, $update);
        }
    }


    public function refund(Order $order, array $meta = []): Order
    {
        $currentStatus = $order->status;

        // Idempotent
        if ($currentStatus === OrderStatus::Refunded->value) {
            return $order;
        }

        OrderStateMachine::assertTransition($currentStatus, OrderStatus::Refunded->value);

        if ($order->getTypeValue() === OrderType::Ecommerce->value) {
            $this->increaseProductQuantities($order);
        }

        $this->giftCardService->reverseForOrder($order);

        $order = $this->orderRepository->update($order, [
            'status' => OrderStatus::Refunded->value,
            'refunded_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);

        // Re-evaluate the product discount tier now that spend has decreased.
        if ($order->getTypeValue() === OrderType::Ecommerce->value && $order->user_id) {
            app(ProductDiscountTierService::class)->checkAndUpgradeUser($order->user);
        }

        // Send refund notification email
        $email = $this->resolveOrderEmail($order);
        if ($email) {
            Mail::to($email)->queue(new OrderRefundedMail($order));
        }

        return $order;
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $email = $this->resolveOrderEmail($order);
        if (!$email) {
            return;
        }

        Mail::to($email)->queue(new OrderConfirmedMail($order, $email));

        // Also notify admin
        $this->sendAdminOrderNotification($order);
    }

    public function sendAdminOrderNotification(Order $order): void
    {
        // Get Super Admin email
        $superAdmin = User::whereHas('role', fn($q) => $q->where('slug', 'superadmin'))->first();
        if (!$superAdmin || !$superAdmin->email) {
            return;
        }

        Mail::to($superAdmin->email)->queue(new NewOrderAdminNotificationMail($order));
    }

    public function updateDeliveryStatus(Order $order, string $deliveryStatus): Order
    {
        if ($order->getTypeValue() !== OrderType::Ecommerce->value) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                ApiResponse::error(
                    ['delivery_status' => ['Delivery status can only be updated for ecommerce orders']],
                    'Invalid order type',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                )
            );
        }

        $previousStatus = $order->delivery_status;

        $updateData = [
            'delivery_status' => $deliveryStatus,
            'delivery_status_updated_at' => now(),
        ];

        if ($deliveryStatus === DeliveryStatus::Delivered->value) {
            $updateData['status'] = OrderStatus::Fulfilled->value;
        }

        if ($deliveryStatus === DeliveryStatus::Canceled->value) {
            $updateData['status'] = OrderStatus::Canceled->value;

            // Refund payment if the order was paid
            if ($order->paid_at && $order->status !== OrderStatus::Refunded->value) {
                try {
                    $order->loadMissing('latestPayment');
                    if ($order->latestPayment) {
                        $this->paymentService->refundOrderPayment($order, [
                            'reason' => 'delivery_canceled',
                        ]);
                        $updateData['status'] = OrderStatus::Refunded->value;
                        $updateData['refunded_at'] = now();
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Auto-refund failed for order #' . $order->id . ': ' . $e->getMessage());
                }
            }

            // Stock is restored exactly once. Skip if the order is already in
            // a state where stock has already been returned (cancelled,
            // refunded, return_approved) — otherwise marking delivery
            // canceled on a previously-cancelled order would double-credit.
            $alreadyRestored = in_array($order->status, [
                OrderStatus::Canceled->value,
                OrderStatus::Refunded->value,
                OrderStatus::ReturnApproved->value,
            ], true);
            if (!$alreadyRestored) {
                $this->increaseProductQuantities($order);
            }
        }

        $order = $this->orderRepository->update($order, $updateData);

        if ($previousStatus !== $deliveryStatus) {
            $this->sendDeliveryStatusUpdateEmail($order, $deliveryStatus);
        }

        return $order;
    }

    public function updateStatus(Order $order, string $status, ?string $note = null, ?int $createdBy = null): Order
    {
        OrderStateMachine::assertTransition($order->status, $status);

        $order = $this->orderRepository->update($order, [
            'status' => $status,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $status,
            'note' => $note,
            'created_by' => $createdBy ?? auth()->id(),
        ]);

        return $order;
    }

    public function getPaginatedOrders(?OrderFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->orderRepository->paginateWithFilter($filter, $perPage, $page);
    }

    public function getPaginatedOrdersForUser(int $userId, ?OrderFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $orders = $this->orderRepository->paginateWithFilterForUser($userId, $filter, $perPage, $page);

        $orders->getCollection()->each(function (Order $order) {
            if ($order->orderable instanceof Booking) {
                $order->loadMissing('orderable.services.bookable');
            }
        });

        return $orders;
    }

    protected function sendDeliveryStatusUpdateEmail(Order $order, string $deliveryStatus): void
    {
        $email = $this->resolveOrderEmail($order);
        if ($email) {
            Mail::to($email)->queue(new OrderDeliveryStatusUpdatedMail($order, $deliveryStatus));
        }
    }

    public function getCustomerEmail(Order $order): ?string
    {
        return $this->resolveOrderEmail($order);
    }

    protected function resolveOrderEmail(Order $order): ?string
    {
        if ($order->user_id) {
            $order->load('user');
            if ($order->user) {
                return $order->user->email;
            }
        }
        return ($order->meta ?? [])['customer_email'] ?? null;
    }

    protected function makeReference(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . Str::upper(bin2hex(random_bytes(4)));
    }

    protected function decreaseProductQuantities(Order $order): void
    {
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }

        foreach ($order->items as $item) {
            if ($item->product_id && $item->quantity > 0) {
                $product = Product::lockForUpdate()->find($item->product_id);
                
                if ($product) {
                    $previousQuantity = $product->max_quantity;
                    $newQuantity = max(0, $previousQuantity - $item->quantity);
                    $product->update(['max_quantity' => $newQuantity]);
                }
            }
        }
    }

    protected function increaseProductQuantities(Order $order): void
    {
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }

        foreach ($order->items as $item) {
            if ($item->product_id && $item->quantity > 0) {
                Product::where('id', $item->product_id)
                    ->increment('max_quantity', $item->quantity);
            }
        }
    }
}
