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
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\ApiResponse;
use App\Services\PaymentService;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected BookingRepositoryInterface $bookingRepository,
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

        if (!$wasAlreadyPaid && $order->getTypeValue() === OrderType::Ecommerce->value) {
            $this->decreaseProductQuantities($order);
        }

        return $order;
    }

    public function cancel(Order $order, array $meta = []): Order
    {
        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Canceled->value,
            'cancelled_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }

    public function cancelBookingForOrder(Order $order): void
    {
        if (!$order->orderable || $order->getTypeValue() !== 'booking') {
            return;
        }
        $booking = $order->orderable;
        if ($booking instanceof Booking) {
            $this->bookingRepository->update($booking, [
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
            ]);
        }
    }


    public function refund(Order $order, array $meta = []): Order
    {
        if ($order->getTypeValue() === OrderType::Ecommerce->value && $order->status === OrderStatus::Paid->value) {
            $this->increaseProductQuantities($order);
        }

        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Refunded->value,
            'refunded_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $email = $this->resolveOrderEmail($order);
        if (!$email) {
            return;
        }

        Mail::to($email)->queue(new OrderConfirmedMail($order, $email));
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
        return 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
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
