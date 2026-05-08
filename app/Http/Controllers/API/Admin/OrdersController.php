<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\OrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\StoreInStoreOrderRequest;
use App\Http\Requests\UpdateOrderDeliveryStatusRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\AdminOrderResource;
use App\Http\Resources\OrderResource;
use App\Models\Booking;
use App\Models\Order;
use App\Services\ApiResponse;
use App\Services\LoyaltyService;
use App\Services\OrderExportService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected OrderExportService $orderExportService,
        protected PaymentService $paymentService,
        protected LoyaltyService $loyaltyService
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->all();
        $sendEmail = (bool) ($data['send_email'] ?? false);

        $order = $this->orderService->createManually($data, $sendEmail);
        $this->loadOrderForDetail($order);

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ], __('success.order.created'));
    }

    public function storeInStore(StoreInStoreOrderRequest $request): JsonResponse
    {
        $data = $request->all();
        $sendEmail = (bool) ($data['send_email'] ?? false);

        $order = $this->orderService->createInStore($data, $sendEmail);
        $this->loadOrderForDetail($order);

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ], __('success.order.created'));
    }

    public function index(Request $request, OrderFilter $filter): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $page    = (int) $request->input('page', 1);

        $orders = $this->orderService->getPaginatedOrders($filter, $perPage, $page);

        $orders->getCollection()->each(function (Order $order) {
            if ($order->orderable instanceof Booking) {
                $order->loadMissing('orderable.services.bookable');
            }
        });

        $resolved = AdminOrderResource::collection($orders->getCollection())->resolve();

        return ApiResponse::success([
            'orders' => $resolved['data'] ?? $resolved,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
            'links' => [
                'first' => $orders->url(1),
                'last'  => $orders->url($orders->lastPage()),
                'prev'  => $orders->previousPageUrl(),
                'next'  => $orders->nextPageUrl(),
            ],
        ], __('success.orders.listed'));
    }

    public function show(Order $order): JsonResponse
    {
        $this->loadOrderForDetail($order);

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ]);
    }

    public function updateDeliveryStatus(UpdateOrderDeliveryStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->orderService->updateDeliveryStatus($order, $request->input('delivery_status'));
        $this->loadOrderForDetail($order);

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ], __('success.order.updated'));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order = $this->orderService->updateStatus(
            $order,
            $request->input('status'),
            $request->input('note')
        );
        $this->loadOrderForDetail($order);

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ], __('success.order.updated'));
    }

    public function refund(Request $request, Order $order): JsonResponse
    {
        $currentStatus = $order->status;

        if (in_array($currentStatus, ['refunded', 'cancelled', 'pending', 'pending_payment'])) {
            return ApiResponse::error(
                ['status' => "Cannot refund an order with status: {$currentStatus}"],
                'Cannot refund this order.',
                422
            );
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $request) {
                // If a Stripe payment exists, the gateway refund must succeed
                // before we change any local status — otherwise we'd mark an
                // order "refunded" while the customer never gets their money.
                if ($order->latestPayment && $order->latestPayment->provider === 'stripe' && $order->latestPayment->external_id) {
                    $this->paymentService->refundOrderPayment($order, [
                        'reason' => $request->input('reason', 'Admin initiated refund'),
                    ]);
                }

                // Update booking status if it's a booking order
                if ($order->type === 'booking' && $order->orderable instanceof Booking) {
                    $booking = $order->orderable;
                    $booking->update([
                        'payment_status' => 'refunded',
                    ]);

                    // Also update all batch bookings
                    if ($booking->batch_id) {
                        Booking::where('batch_id', $booking->batch_id)
                            ->where('id', '!=', $booking->id)
                            ->update(['payment_status' => 'refunded']);
                    }
                }

                $this->orderService->refund($order, [
                    'refunded_by' => auth()->id(),
                    'refund_reason' => $request->input('reason', 'Admin initiated refund'),
                ]);
            });

            $order->refresh();

            // Refunded bookings stop counting toward loyalty thresholds — re-run
            // the tier evaluation so the user's referral_id (and the "Your
            // Discount" panel) reflects their new qualifying visit count.
            if ($order->type === 'booking' && $order->orderable instanceof Booking) {
                $booking = $order->orderable->fresh();
                $userIds = collect();
                if ($booking && $booking->user_id) {
                    $userIds->push($booking->user_id);
                }
                if ($booking && $booking->batch_id) {
                    Booking::where('batch_id', $booking->batch_id)
                        ->whereNotNull('user_id')
                        ->pluck('user_id')
                        ->each(fn($id) => $userIds->push($id));
                }
                $userIds->unique()->each(function ($userId) {
                    $u = \App\Models\User::find($userId);
                    if ($u) {
                        $this->loyaltyService->checkAndUpgradeUser($u);
                    }
                });
            }

            $this->loadOrderForDetail($order);

            return ApiResponse::success([
                'order' => new AdminOrderResource($order),
            ], 'Order refunded successfully.');
        } catch (\Throwable $e) {
            \Log::error('OrdersController::refund error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(null, $e->getMessage(), 422);
        }
    }

    protected function loadOrderForDetail(Order $order): void
    {
        $order->load([
            'user',
            'items.product',
            'shippingAddress.country',
            'billingAddress.country',
            'latestPayment',
            'statusHistory.createdBy',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }
    }

    public function downloadInvoicePdf(Order $order)
    {
        return $this->orderExportService->downloadInvoicePdf($order);
    }

    public function downloadInvoiceXlsx(Order $order): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->orderExportService->downloadInvoiceXlsx($order);
    }

    public function exportOrdersPdf(Request $request, OrderFilter $filter)
    {
        $ids = $this->parseExportIds($request);

        return $this->orderExportService->exportOrdersPdf(
            $ids ? null : $filter,
            $ids ?: null
        );
    }

    public function exportOrdersXlsx(Request $request, OrderFilter $filter): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $ids = $this->parseExportIds($request);

        return $this->orderExportService->exportOrdersXlsx(
            $ids ? null : $filter,
            $ids ?: null
        );
    }

    /** @return array<int>|null */
    protected function parseExportIds(Request $request): ?array
    {
        $ids = $request->input('ids');
        if (!$ids) {
            return null;
        }
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));

        return $ids ?: null;
    }
}
