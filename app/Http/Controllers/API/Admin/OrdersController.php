<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderDeliveryStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\ApiResponse;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'latestPayment',
        ]);

        // For booking orders, load booking services
        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ]);
    }

    public function updateDeliveryStatus(UpdateOrderDeliveryStatusRequest $request, Order $order): JsonResponse
    {
        $deliveryStatus = $request->input('delivery_status'); // Mapped from deliveryStatus

        $order = $this->orderService->updateDeliveryStatus($order, $deliveryStatus);

        $order->load([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'latestPayment',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ], __('success.order.updated'));
    }
}
