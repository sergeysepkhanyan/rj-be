<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filters\OrderFilter;
use App\Mail\OrderConfirmedMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Services\ApiResponse;
use App\Services\PaymentService;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
            'cancelled_at' => now(),
            'meta'   => array_merge($order->meta ?? [], $meta),
        ]);
    }

    public function cancelOrder(Order $order, ?string $reason = null, ?int $userId = null): Order
    {
        $user = $userId ? \App\Models\User::find($userId) : auth()->user();
        $isAdmin = $user?->isAdmin() ?? false;

        if ($order->status === OrderStatus::Canceled->value) {
            return $order;
        }

        if ($order->status === OrderStatus::Refunded->value) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                ApiResponse::error(
                    ['order' => ['Order has already been refunded']],
                    'Cannot cancel refunded order',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                )
            );
        }

        if (!$isAdmin && $order->user_id && (int)$order->user_id !== (int)($user?->id ?? 0)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                ApiResponse::error(
                    ['order' => ['You can only cancel your own orders']],
                    'Unauthorized',
                    Response::HTTP_FORBIDDEN
                )
            );
        }

        $canRefund = false;
        $appointmentDateTime = null;

        if ($order->type === OrderType::Booking->value && $order->orderable instanceof Booking) {
            $booking = $order->orderable;
            $booking->loadMissing('services');
            
            if ($booking->services->isNotEmpty()) {
                $firstService = $booking->services->sortBy('start_time')->first();
                $date = $booking->date;
                $startTime = $firstService->start_time ?? $booking->start_time;
                $timezone = $firstService->timezone ?? $booking->timezone ?? 'UTC';
                
                $timeStr = (string) $startTime;
                if (strlen($timeStr) === 5) {
                    $timeStr .= ':00';
                }
                
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeStr}", $timezone);
                $hoursUntilAppointment = now($timezone)->diffInHours($appointmentDateTime, false);
                
                $canRefund = $hoursUntilAppointment >= 24;
            }
        } elseif ($order->type === OrderType::Ecommerce->value) {
            $canRefund = true;
        }

        $shouldRefund = $canRefund && $order->status === OrderStatus::Paid->value;
        
        if ($shouldRefund) {
            $order->load('latestPayment');
            if ($order->latestPayment) {
                $this->paymentService->refundOrderPayment($order, [
                    'order_id' => (string) $order->id,
                    'reason' => $reason ?? 'order_cancelled',
                ]);
                $this->refund($order, [
                    'reason' => $reason ?? 'order_cancelled',
                    'cancelled_by_user_id' => $user?->id,
                ]);
            }
        } else {
            $this->cancel($order, [
                'reason' => $reason,
                'cancelled_by_user_id' => $user?->id,
                'refund_available' => false,
                'refund_reason' => $canRefund ? null : 'Cancellation must be at least 24 hours before appointment',
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $shouldRefund ? OrderStatus::Refunded->value : OrderStatus::Canceled->value,
            'note' => $reason ?? ($shouldRefund ? 'Cancelled with refund' : 'Cancelled without refund'),
            'created_by' => $user?->id,
        ]);

        return $order->fresh();
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

    protected function makeReference(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }
}
