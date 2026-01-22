<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filters\OrderFilter;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderDeliveryStatusUpdatedMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\ApiResponse;
use App\Services\PaymentService;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected PaymentService $paymentService
    ) {}

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
        $wasAlreadyPaid = $order->status === OrderStatus::Paid->value;
        $previousStatus = $order->status;

        Log::info('[order][markPaid] Starting markPaid process', [
            'order_id' => $order->id,
            'order_type' => $order->type,
            'previous_status' => $previousStatus,
            'was_already_paid' => $wasAlreadyPaid,
        ]);

        $order = $this->orderRepository->update($order, [
            'status' => OrderStatus::Paid->value, // Explicitly use ->value to ensure status is updated
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);

        $order->refresh(); // Ensure we have the latest status

        Log::info('[order][markPaid] Order status updated', [
            'order_id' => $order->id,
            'previous_status' => $previousStatus,
            'new_status' => $order->status,
            'status_changed' => $previousStatus !== $order->status,
        ]);

        // Decrease product quantities for ecommerce orders (only if not already paid)
        if (!$wasAlreadyPaid && $order->type === OrderType::Ecommerce->value) {
            Log::info('[order][markPaid] Decreasing product quantities', [
                'order_id' => $order->id,
                'order_type' => $order->type,
            ]);
            $this->decreaseProductQuantities($order);
            Log::info('[order][markPaid] Product quantities decreased', [
                'order_id' => $order->id,
            ]);
        } else {
            Log::info('[order][markPaid] Skipping quantity decrease', [
                'order_id' => $order->id,
                'was_already_paid' => $wasAlreadyPaid,
                'order_type' => $order->type,
                'is_ecommerce' => $order->type === OrderType::Ecommerce->value,
            ]);
        }

        Log::info('[order][markPaid] markPaid completed', [
            'order_id' => $order->id,
            'final_status' => $order->status,
        ]);

        return $order;
    }

    public function cancel(Order $order, array $meta = []): Order
    {
        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Canceled->value, // Explicitly use ->value to ensure status is updated
            'cancelled_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }


    public function refund(Order $order, array $meta = []): Order
    {
        // Increase product quantities back for ecommerce orders before updating status
        if ($order->type === OrderType::Ecommerce->value && $order->status === OrderStatus::Paid->value) {
            $this->increaseProductQuantities($order);
        }

        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Refunded->value, // Explicitly use ->value to ensure status is updated
            'refunded_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }

    public function sendOrderConfirmation(Order $order): void
    {
        Log::info('[order][email] Starting order confirmation email', [
            'order_id' => $order->id,
            'order_type' => $order->type,
        ]);

        // Get customer email from order
        $email = null;

        // First try to get from user relationship
        if ($order->user_id) {
            $order->load('user');
            if ($order->user) {
                $email = $order->user->email;
            }
        }

        // Fallback to meta if user email not available
        if (!$email && $order->meta && isset($order->meta['customer_email'])) {
            $email = $order->meta['customer_email'];
        }

        if ($email) {
            Log::info('[order][email] Sending order confirmation email', [
                'order_id' => $order->id,
                'email' => $email,
            ]);
            Mail::to($email)->queue(new OrderConfirmedMail($order));
            Log::info('[order][email] Order confirmation email queued', [
                'order_id' => $order->id,
                'email' => $email,
            ]);
        } else {
            Log::warning('[order][email] No email found for order confirmation', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'has_meta' => !empty($order->meta),
            ]);
        }
    }

    public function updateDeliveryStatus(Order $order, string $deliveryStatus): Order
    {
        if ($order->type !== OrderType::Ecommerce->value) {
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
            $updateData['status'] = OrderStatus::Fulfilled->value; // Explicitly use ->value to ensure status is updated
        }

        $order = $this->orderRepository->update($order, $updateData);

        if ($previousStatus !== $deliveryStatus) {
            $this->sendDeliveryStatusUpdateEmail($order, $deliveryStatus);
        }

        return $order;
    }

    public function updateStatus(Order $order, string $status, ?string $note = null, ?int $createdBy = null): Order
    {
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

    protected function sendDeliveryStatusUpdateEmail(Order $order, string $deliveryStatus): void
    {
        $email = null;

        if ($order->user_id) {
            $order->load('user');
            if ($order->user) {
                $email = $order->user->email;
            }
        }

        if (!$email && $order->meta && isset($order->meta['customer_email'])) {
            $email = $order->meta['customer_email'];
        }

        if ($email) {
            Mail::to($email)->queue(new OrderDeliveryStatusUpdatedMail($order, $deliveryStatus));
        }
    }

    protected function makeReference(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }

    /**
     * Decrease product quantities when order is paid.
     * Uses database decrement to avoid race conditions.
     * Prevents negative quantities by using GREATEST to cap at 0.
     */
    protected function decreaseProductQuantities(Order $order): void
    {
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }

        foreach ($order->items as $item) {
            if ($item->product_id && $item->quantity > 0) {
                // Use Eloquent with lockForUpdate to prevent race conditions
                // Calculate new quantity ensuring it never goes below 0
                $product = Product::lockForUpdate()->find($item->product_id);
                
                if ($product) {
                    $previousQuantity = $product->max_quantity;
                    $newQuantity = max(0, $previousQuantity - $item->quantity);
                    $product->update(['max_quantity' => $newQuantity]);
                    
                    Log::info('[order][quantity] Decreased product quantity', [
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'quantity_decreased' => $item->quantity,
                        'previous_quantity' => $previousQuantity,
                        'new_quantity' => $newQuantity,
                    ]);
                }
            }
        }
    }

    /**
     * Increase product quantities back when order is refunded.
     * Uses database increment to avoid race conditions.
     */
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
