<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\OrderService;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected PaymentRepositoryInterface $paymentRepo,
        protected OrderService $orderService,
        protected BookingRepositoryInterface $bookingRepo,
        protected PaymentMethodService $paymentMethodService,
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature');
        $secret = (string) config('stripe.webhook_secret');

        if (!$this->isValidSignature($payload, $sigHeader, $secret)) {
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $event = json_decode($payload, true);
        $type = data_get($event, 'type');
        $object = data_get($event, 'data.object', []);
        $paymentIntentId = data_get($object, 'id');

        if (!$paymentIntentId) {
            return response()->json(['message' => 'Missing payment intent id'], Response::HTTP_BAD_REQUEST);
        }

        $payment = $this->paymentRepo->findByProviderExternalId('stripe', $paymentIntentId);
        
        // Fallback: Try to find payment by order_id from metadata if external_id lookup fails
        if (!$payment) {
            $orderId = data_get($object, 'metadata.order_id');
            if ($orderId) {
                $payment = \App\Models\Payment::where('provider', 'stripe')
                    ->where('order_id', $orderId)
                    ->where(function ($query) use ($paymentIntentId) {
                        $query->whereNull('external_id')
                              ->orWhere('external_id', $paymentIntentId);
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($payment && !$payment->external_id) {
                    // Update the payment with the external_id if it was missing
                    $this->paymentRepo->update($payment, ['external_id' => $paymentIntentId]);
                }
            }
        }
        
        if (!$payment) {
            \Log::warning('[stripe][webhook] Payment not found', [
                'payment_intent_id' => $paymentIntentId,
                'event_type' => $type,
                'order_id_from_metadata' => data_get($object, 'metadata.order_id'),
            ]);
            return response()->json(['ok' => true]);
        }

        \Log::info('[stripe][webhook] Payment found', [
            'payment_id' => $payment->id,
            'payment_intent_id' => $paymentIntentId,
            'current_status' => $payment->status,
            'event_type' => $type,
        ]);

        $order = $payment->order()->with('orderable')->first();
        $paymentUpdate = ['raw' => $object];

        switch ($type) {
            case 'payment_intent.succeeded':
                $paymentUpdate['status'] = 'paid';
                $paymentUpdate['paid_at'] = now();
                if ($order) {
                    $previousOrderStatus = $order->status;
                    \Log::info('[stripe][webhook] Marking order as paid', [
                        'order_id' => $order->id,
                        'previous_order_status' => $previousOrderStatus,
                        'order_type' => $order->type,
                    ]);
                    
                    // Update order status to paid
                    $order = $this->orderService->markPaid($order, ['stripe_payment_intent_id' => $paymentIntentId]);
                    $order->refresh(); // Ensure we have the latest status
                    
                    \Log::info('[stripe][webhook] Order status updated', [
                        'order_id' => $order->id,
                        'previous_status' => $previousOrderStatus,
                        'new_status' => $order->status,
                        'status_changed' => $previousOrderStatus !== $order->status,
                    ]);

                    // Auto-save payment method for logged-in users (if not already saved)
                    if ($order->user_id) {
                        $this->paymentMethodService->ensureStripePaymentMethodSaved(
                            (int) $order->user_id,
                            (string) data_get($object, 'payment_method')
                        );
                    }

                    // Update booking status if this is a booking order
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $previousBookingStatus = $booking->status;
                        $previousPaymentStatus = $booking->payment_status;
                        
                        $this->bookingRepo->update($booking, [
                            'status' => 'confirmed',
                            'payment_status' => 'paid',
                        ]);
                        
                        $booking->refresh();
                        
                        \Log::info('[stripe][webhook] Booking status updated', [
                            'booking_id' => $booking->id,
                            'previous_status' => $previousBookingStatus,
                            'new_status' => $booking->status,
                            'previous_payment_status' => $previousPaymentStatus,
                            'new_payment_status' => $booking->payment_status,
                        ]);
                    } elseif ($order->type === 'ecommerce') {
                        // Send order confirmation email for ecommerce orders
                        $this->orderService->sendOrderConfirmation($order);
                    }
                }
                break;

            case 'payment_intent.payment_failed':
                $paymentUpdate['status'] = 'failed';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $previousOrderStatus = $order->status;
                    $order = $this->orderService->cancel($order, ['reason' => 'payment_failed']);
                    $order->refresh();
                    
                    \Log::info('[stripe][webhook] Order cancelled (payment failed)', [
                        'order_id' => $order->id,
                        'previous_status' => $previousOrderStatus,
                        'new_status' => $order->status,
                    ]);
                    
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $previousBookingStatus = $booking->status;
                        $this->bookingRepo->update($booking, [
                            'status' => 'cancelled',
                            'payment_status' => 'unpaid',
                        ]);
                        $booking->refresh();
                        
                        \Log::info('[stripe][webhook] Booking cancelled (payment failed)', [
                            'booking_id' => $booking->id,
                            'previous_status' => $previousBookingStatus,
                            'new_status' => $booking->status,
                        ]);
                    }
                }
                break;

            case 'payment_intent.canceled':
                $paymentUpdate['status'] = 'cancelled';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $previousOrderStatus = $order->status;
                    $order = $this->orderService->cancel($order, ['reason' => 'canceled']);
                    $order->refresh();
                    
                    \Log::info('[stripe][webhook] Order cancelled (canceled)', [
                        'order_id' => $order->id,
                        'previous_status' => $previousOrderStatus,
                        'new_status' => $order->status,
                    ]);
                    
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $previousBookingStatus = $booking->status;
                        $this->bookingRepo->update($booking, [
                            'status' => 'cancelled',
                            'payment_status' => 'unpaid',
                        ]);
                        $booking->refresh();
                        
                        \Log::info('[stripe][webhook] Booking cancelled (canceled)', [
                            'booking_id' => $booking->id,
                            'previous_status' => $previousBookingStatus,
                            'new_status' => $booking->status,
                        ]);
                    }
                }
                break;

            default:
                $paymentUpdate['status'] = 'pending';
                break;
        }

        \Log::info('[stripe][webhook] Updating payment', [
            'payment_id' => $payment->id,
            'payment_update' => $paymentUpdate,
        ]);

        $this->paymentRepo->update($payment, $paymentUpdate);

        $payment->refresh();
        \Log::info('[stripe][webhook] Payment updated', [
            'payment_id' => $payment->id,
            'new_status' => $payment->status,
            'order_id' => $order?->id,
            'order_status' => $order?->status,
        ]);

        return response()->json(['ok' => true]);
    }

    private function isValidSignature(string $payload, string $sigHeader, string $secret): bool
    {
        if (!$secret || !$sigHeader) {
            return false;
        }

        $parts = array_filter(explode(',', $sigHeader));
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            [$k, $v] = array_map('trim', explode('=', $part, 2));
            if ($k === 't') {
                $timestamp = $v;
            }
            if ($k === 'v1') {
                $signatures[] = $v;
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }
}
