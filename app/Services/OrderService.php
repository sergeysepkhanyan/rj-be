<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Mail\OrderConfirmedMail;
use App\Models\Booking;
use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\ApiResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function createForBooking(Booking $booking, string $paymentMode): Order
    {
        $existing = $this->orderRepository->findByOrderable(Booking::class, $booking->id);
        if ($existing) {
            return $existing;
        }

        $status = ($paymentMode === 'pay_now')
            ? OrderStatus::PendingPayment
            : OrderStatus::Pending;

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
        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Paid,
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }

    public function cancel(Order $order, array $meta = []): Order
    {
        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Canceled,
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }

    public function refund(Order $order, array $meta = []): Order
    {
        return $this->orderRepository->update($order, [
            'status' => OrderStatus::Refunded,
            'refunded_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }

    public function sendOrderConfirmation(Order $order): void
    {
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
            Mail::to($email)->send(new OrderConfirmedMail($order));
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

        $updateData = [
            'delivery_status' => $deliveryStatus,
            'delivery_status_updated_at' => now(),
        ];

        if ($deliveryStatus === DeliveryStatus::Delivered->value) {
            $updateData['status'] = OrderStatus::Fulfilled;
        }

        return $this->orderRepository->update($order, $updateData);
    }

    protected function makeReference(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }
}
