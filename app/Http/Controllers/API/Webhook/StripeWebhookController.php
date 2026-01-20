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
        if (!$payment) {
            return response()->json(['ok' => true]);
        }

        $order = $payment->order()->with('orderable')->first();
        $paymentUpdate = ['raw' => $object];

        switch ($type) {
            case 'payment_intent.succeeded':
                $paymentUpdate['status'] = 'paid';
                $paymentUpdate['paid_at'] = now();
                if ($order) {
                    $this->orderService->markPaid($order, ['stripe_payment_intent_id' => $paymentIntentId]);

                    // Auto-save payment method for logged-in users (if not already saved)
                    if ($order->user_id) {
                        $this->paymentMethodService->ensureStripePaymentMethodSaved(
                            (int) $order->user_id,
                            (string) data_get($object, 'payment_method')
                        );
                    }

                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, [
                            'status' => 'confirmed',
                            'payment_status' => 'paid',
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
                    $this->orderService->cancel($order, ['reason' => 'payment_failed']);
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, [
                            'status' => 'cancelled',
                            'payment_status' => 'unpaid',
                        ]);
                    }
                }
                break;

            case 'payment_intent.canceled':
                $paymentUpdate['status'] = 'cancelled';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $this->orderService->cancel($order, ['reason' => 'canceled']);
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, [
                            'status' => 'cancelled',
                            'payment_status' => 'unpaid',
                        ]);
                    }
                }
                break;

            default:
                $paymentUpdate['status'] = 'pending';
                break;
        }

        $this->paymentRepo->update($payment, $paymentUpdate);

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
