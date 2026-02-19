<?php

namespace App\Http\Controllers\API;

use App\Enums\OrderStatus;
use App\Filters\OrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Integrations\Stripe\StripeClient;
use App\Models\Order;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\ApiResponse;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentRepositoryInterface $paymentRepo,
        protected StripeClient $stripeClient,
    ) {}

    public function index(Request $request, OrderFilter $filter): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        $orders = $this->orderService->getPaginatedOrdersForUser(auth()->id(), $filter, $perPage, $page);

        return ApiResponse::success([
            'orders' => OrderResource::collection($orders),
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

    /**
     * Verify and confirm payment for an order.
     * This is used when webhooks can't reach localhost (development).
     * The frontend calls this after Stripe confirms payment client-side.
     */
    public function verifyPayment(Request $request, int $orderId): JsonResponse
    {
        $paymentIntentId = $request->input('payment_intent_id');

        if (!$paymentIntentId) {
            return ApiResponse::error(
                ['payment_intent_id' => ['Payment intent ID is required']],
                'Validation failed',
                422
            );
        }

        // Find the order
        $order = Order::with(['latestPayment', 'items', 'shippingAddress.country', 'billingAddress.country'])->find($orderId);

        if (!$order) {
            return ApiResponse::error(
                ['order' => ['Order not found']],
                'Not found',
                404
            );
        }

        // Check if order is already paid
        if ($order->status === OrderStatus::Paid->value) {
            return ApiResponse::success([
                'order' => new OrderResource($order),
                'verified' => true,
                'already_paid' => true,
            ], 'Order already paid');
        }

        // Verify with Stripe API
        try {
            $paymentIntent = $this->stripeClient->retrievePaymentIntent($paymentIntentId);
            $paymentStatus = $paymentIntent['status'] ?? null;

            if ($paymentStatus === 'succeeded') {
                // Update payment record
                $payment = $order->latestPayment;
                if ($payment) {
                    $this->paymentRepo->update($payment, [
                        'status' => 'paid',
                        'paid_at' => now(),
                        'external_id' => $paymentIntentId,
                    ]);
                }

                // Mark order as paid
                $order = $this->orderService->markPaid($order, [
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'verified_via' => 'api',
                ]);

                // Send confirmation email for ecommerce orders
                if ($order->getTypeValue() === 'ecommerce') {
                    $this->orderService->sendOrderConfirmation($order);
                }

                $order->refresh();
                $order->load(['items', 'shippingAddress.country', 'billingAddress.country']);

                return ApiResponse::success([
                    'order' => new OrderResource($order),
                    'verified' => true,
                ], 'Payment verified successfully');
            }

            return ApiResponse::error(
                ['payment' => ["Payment status is '{$paymentStatus}', not succeeded"]],
                'Payment not verified',
                400
            );
        } catch (\Throwable $e) {
            \Log::error('[orders][verify-payment] Failed to verify payment', [
                'order_id' => $orderId,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                ['payment' => ['Failed to verify payment with Stripe']],
                'Verification failed',
                500
            );
        }
    }

    /**
     * Get a single order by ID.
     */
    public function show(int $orderId): JsonResponse
    {
        $order = Order::with(['items.product', 'shippingAddress.country', 'billingAddress.country', 'latestPayment'])
            ->where(function ($query) {
                // Allow authenticated users to see their own orders, or guest orders by session
                if (auth()->check()) {
                    $query->where('user_id', auth()->id());
                }
            })
            ->find($orderId);

        if (!$order) {
            return ApiResponse::error(
                ['order' => ['Order not found']],
                'Not found',
                404
            );
        }

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ]);
    }
}
