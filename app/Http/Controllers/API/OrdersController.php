<?php

namespace App\Http\Controllers\API;

use App\Enums\OrderStatus;
use App\Filters\OrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Integrations\Stripe\StripeClient;
use App\Models\Booking;
use App\Models\Order;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\ApiResponse;
use App\Services\BookingService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected PaymentRepositoryInterface $paymentRepo,
        protected StripeClient $stripeClient,
        protected BookingRepositoryInterface $bookingRepo,
        protected BookingService $bookingService,
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
        $order = Order::with(['latestPayment', 'items', 'shippingAddress.country', 'billingAddress.country', 'orderable'])->find($orderId);

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

                // Handle order type specific actions
                if ($order->getTypeValue() === 'gift_card') {
                    // Create gift card purchase on payment success
                    $this->handleGiftCardPaymentSuccess($order);
                } elseif ($order->getTypeValue() === 'ecommerce') {
                    // Send confirmation email for ecommerce orders
                    $this->orderService->sendOrderConfirmation($order);
                } elseif ($order->getTypeValue() === 'booking' && $order->orderable instanceof Booking) {
                    // Update booking status for booking orders
                    $booking = $order->orderable;
                    $this->bookingRepo->update($booking, [
                        'status' => 'confirmed',
                        'payment_status' => 'paid',
                    ]);
                    $booking->refresh();

                    // If this booking is part of a batch, mark all bookings in the batch as paid
                    if ($booking->batch_id) {
                        $this->bookingService->markBatchBookingsPaid($booking->batch_id);
                        // Send confirmation for all bookings in batch
                        $batchBookings = $this->bookingService->getBookingsByBatchId($booking->batch_id);
                        foreach ($batchBookings as $batchBooking) {
                            $this->bookingService->sendBookingConfirmation($batchBooking);
                        }
                    } else {
                        // Send booking confirmation email
                        $this->bookingService->sendBookingConfirmation($booking);
                    }
                }

                $order->refresh();
                $order->load(['items', 'shippingAddress.country', 'billingAddress.country', 'orderable']);

                $response = [
                    'order' => new OrderResource($order),
                    'verified' => true,
                ];

                // Include gift card purchase code in response
                if ($order->getTypeValue() === 'gift_card') {
                    $purchase = \App\Models\GiftCardPurchase::where('order_id', $order->id)->first();
                    if ($purchase) {
                        $response['purchase'] = [
                            'code' => $purchase->code,
                            'expiresAt' => $purchase->expires_at->toIso8601String(),
                        ];
                    }
                }

                return ApiResponse::success($response, 'Payment verified successfully');
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

    private function handleGiftCardPaymentSuccess($order): void
    {
        $meta = $order->meta ?? [];
        $giftCardId = $meta['gift_card_id'] ?? null;
        if (!$giftCardId) return;

        // Don't create duplicate
        if (\App\Models\GiftCardPurchase::where('order_id', $order->id)->exists()) return;

        $giftCard = \App\Models\GiftCard::find($giftCardId);
        if (!$giftCard) return;

        $purchase = \App\Models\GiftCardPurchase::create([
            'gift_card_id' => $giftCard->id,
            'order_id' => $order->id,
            'code' => \App\Models\GiftCardPurchase::generateCode(),
            'buyer_name' => $meta['customer_name'] ?? 'Unknown',
            'buyer_email' => $meta['customer_email'] ?? '',
            'buyer_phone' => $meta['customer_phone'] ?? null,
            'recipient_name' => $meta['recipient_name'] ?? 'Unknown',
            'recipient_email' => $meta['recipient_email'] ?? null,
            'amount' => $giftCard->price,
            'balance' => $giftCard->price,
            'currency' => $giftCard->currency,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        $purchase->load('giftCard');

        if ($purchase->buyer_email) {
            \Illuminate\Support\Facades\Mail::to($purchase->buyer_email)
                ->queue(new \App\Mail\GiftCardPurchasedMail($purchase, 'buyer'));
        }
        if ($purchase->recipient_email) {
            \Illuminate\Support\Facades\Mail::to($purchase->recipient_email)
                ->queue(new \App\Mail\GiftCardPurchasedMail($purchase, 'recipient'));
        }

        // Create lead for non-registered buyers
        if (!$order->user_id && $purchase->buyer_phone) {
            $phone = $purchase->buyer_phone;
            if (!\App\Models\User::where('mobile', $phone)->orWhere('email', $purchase->buyer_email)->exists()) {
                if (!\App\Models\Lead::where('phone', $phone)->exists()) {
                    \App\Models\Lead::create([
                        'name' => $purchase->buyer_name,
                        'phone' => $phone,
                        'email' => $purchase->buyer_email,
                        'source' => 'order',
                        'status' => 'new',
                    ]);
                }
            }
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
