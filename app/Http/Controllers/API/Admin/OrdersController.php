<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\OrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderDeliveryStatusRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Booking;
use App\Models\Order;
use App\Services\ApiResponse;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request, OrderFilter $filter): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $page    = (int) $request->input('page', 1);

        $orders = $this->orderService->getPaginatedOrders($filter, $perPage, $page);

        // Load additional relationships for booking orders
        $orders->getCollection()->each(function (Order $order) {
            if ($order->orderable instanceof Booking) {
                $order->loadMissing('orderable.services.bookable');
            }
        });

        // Format orders for admin table view
        $formattedOrders = $orders->map(function (Order $order) {
            $productName = null;
            $productId = null;
            $quantity = 0;
            $address = null;
            $status = $order->delivery_status ?? $order->status;

            // Format status for display
            $displayStatus = match ($status) {
                'delivered', 'fulfilled' => 'Delivered',
                'paid' => 'In Process',
                default => ucfirst(str_replace('_', ' ', $status)),
            };

            if ($order->type === 'ecommerce') {
                // Get first product for ecommerce orders
                if ($order->relationLoaded('items') && $order->items->isNotEmpty()) {
                    $firstItem = $order->items->first();
                    if ($firstItem && $firstItem->relationLoaded('product') && $firstItem->product) {
                        $productName = $firstItem->product->name;
                        $productId = $firstItem->product_id;
                    } else {
                        $productId = $firstItem->product_id ?? null;
                    }
                    $quantity = (int) $order->items->sum('quantity');
                } else {
                    // If items not loaded, try to load them
                    $order->load('items.product');
                    if ($order->items->isNotEmpty()) {
                        $firstItem = $order->items->first();
                        if ($firstItem && $firstItem->product) {
                            $productName = $firstItem->product->name;
                            $productId = $firstItem->product_id;
                        }
                        $quantity = (int) $order->items->sum('quantity');
                    }
                }
            } elseif ($order->type === 'booking') {
                if ($order->relationLoaded('orderable') && $order->orderable instanceof Booking) {
                    $booking = $order->orderable;
                    if ($booking->relationLoaded('services') && $booking->services->isNotEmpty()) {
                        $firstService = $booking->services->first();
                        if ($firstService && $firstService->relationLoaded('bookable') && $firstService->bookable) {
                            $productName = $firstService->bookable->name;
                            $productId = $firstService->bookable_id;
                        } else {
                            $productId = $firstService->bookable_id ?? null;
                        }
                        $quantity = $booking->services->count();
                    } else {
                        // If services not loaded, try to load them
                        $booking->load('services.bookable');
                        if ($booking->services->isNotEmpty()) {
                            $firstService = $booking->services->first();
                            if ($firstService && $firstService->bookable) {
                                $productName = $firstService->bookable->name;
                                $productId = $firstService->bookable_id;
                            }
                            $quantity = $booking->services->count();
                        }
                    }
                }
            }

            // Format address
            if ($order->relationLoaded('shippingAddress') && $order->shippingAddress) {
                $parts = array_filter([
                    $order->shippingAddress->address,
                    $order->shippingAddress->city,
                    $order->shippingAddress->state,
                ]);
                $address = $parts ? implode(', ', $parts) : null;
            }

            // Get payment information
            $paymentId = null;
            $paymentMethod = null;
            $paymentMethodLast4 = null;
            $paymentMethodBrand = null;

            if ($order->relationLoaded('latestPayment') && $order->latestPayment) {
                $payment = $order->latestPayment;
                
                // Payment ID - use external_id (Stripe PaymentIntent ID) or format from order reference
                if ($payment->external_id) {
                    $paymentId = $payment->external_id;
                } else {
                    $paymentId = $order->reference ?? "#{$order->id}";
                }

                // Get payment method info
                if ($payment->relationLoaded('paymentMethod') && $payment->paymentMethod) {
                    // From saved payment method
                    $paymentMethodLast4 = $payment->paymentMethod->last4;
                    $paymentMethodBrand = $payment->paymentMethod->brand;
                } elseif ($payment->provider === 'stripe' && $payment->raw) {
                    // From Stripe raw data
                    $raw = $payment->raw;
                    
                    // Try to get from payment_method_details (from charge)
                    $charge = data_get($raw, 'charges.data.0');
                    if ($charge) {
                        $paymentMethodLast4 = data_get($charge, 'payment_method_details.card.last4');
                        $paymentMethodBrand = data_get($charge, 'payment_method_details.card.brand');
                    }
                    
                    // Fallback: try from payment_method object if expanded
                    if (!$paymentMethodLast4) {
                        $pm = data_get($raw, 'payment_method');
                        if (is_array($pm)) {
                            $paymentMethodLast4 = data_get($pm, 'card.last4');
                            $paymentMethodBrand = data_get($pm, 'card.brand');
                        }
                    }
                }

                // Format payment method display
                if ($paymentMethodLast4) {
                    $brand = $paymentMethodBrand ? ucfirst($paymentMethodBrand) : 'Card';
                    $paymentMethod = "{$brand} ...{$paymentMethodLast4}";
                } else {
                    $paymentMethod = $payment->provider === 'stripe' ? 'Card' : ucfirst($payment->provider ?? 'Unknown');
                }
            }

            return [
                'id' => $order->id,
                'paymentId' => $paymentId,
                'productName' => $productName,
                'productId' => $productId,
                'paymentMethod' => $paymentMethod,
                'paymentMethodLast4' => $paymentMethodLast4,
                'paymentMethodBrand' => $paymentMethodBrand,
                'price' => (string) $order->amount,
                'quantity' => $quantity,
                'address' => $address,
                'date' => $order->created_at?->format('d, M Y'),
                'status' => $displayStatus,
                'type' => $order->type,
                'reference' => $order->reference,
            ];
        });

        return ApiResponse::success([
            'orders' => $formattedOrders,
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
        $order->load([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'latestPayment',
            'statusHistory.createdBy',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ]);
    }

    public function updateDeliveryStatus(UpdateOrderDeliveryStatusRequest $request, Order $order): JsonResponse
    {
        $deliveryStatus = $request->input('delivery_status');

        $order = $this->orderService->updateDeliveryStatus($order, $deliveryStatus);

        $order->load([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'latestPayment',
            'statusHistory.createdBy',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ], __('success.order.updated'));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $status = $request->input('status');
        $note = $request->input('note');

        $order = $this->orderService->updateStatus($order, $status, $note);

        $order->load([
            'user',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'latestPayment',
            'statusHistory.createdBy',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        return ApiResponse::success([
            'order' => new OrderResource($order),
        ], __('success.order.updated'));
    }
}
