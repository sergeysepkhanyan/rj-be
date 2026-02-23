<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Integrations\Stripe\StripeClient;
use App\Jobs\Zoho\SyncOrderToZohoJob;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Enums\OrderStatus;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected PaymentRepositoryInterface $paymentRepo,
        protected OrderService $orderService,
        protected BookingRepositoryInterface $bookingRepo,
        protected PaymentMethodService $paymentMethodService,
        protected BookingService $bookingService,
        protected StripeClient $stripeClient,
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature');
        $secret = (string) config('stripe.webhook_secret');

        if (!$this->isValidSignature($payload, $sigHeader, $secret)) {
            \Log::warning('[stripe][webhook] Invalid signature');
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
                    $this->paymentRepo->update($payment, ['external_id' => $paymentIntentId]);
                }
            }
        }

        if (!$payment) {
            \Log::warning('[stripe][webhook] Payment not found', ['payment_intent_id' => $paymentIntentId, 'event_type' => $type]);
            return response()->json(['ok' => true]);
        }

        $order = $payment->order()->with('orderable')->first();

        if (!$order) {
            \Log::error('[stripe][webhook] Order not found for payment', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntentId,
                'order_id' => $payment->order_id,
            ]);
            return response()->json(['ok' => true]);
        }

        $paymentUpdate = ['raw' => $object];

        switch ($type) {
            case 'payment_intent.succeeded':
                $paymentUpdate['status'] = 'paid';
                $paymentUpdate['paid_at'] = now();

                try {
                    DB::beginTransaction();

                    $previousOrderStatus = $order->status;
                    $previousPaymentStatus = $payment->status;

                    $this->paymentRepo->update($payment, $paymentUpdate);
                    $payment->refresh();

                    $wasAlreadyPaid = $previousOrderStatus === OrderStatus::Paid->value;

                    $order = $this->orderService->markPaid($order, ['stripe_payment_intent_id' => $paymentIntentId]);
                    $order->refresh();

                    if ($order->user_id) {
                        $paymentMethodId = (string) data_get($object, 'payment_method');
                        if ($paymentMethodId) {
                            $customerFromIntent = data_get($object, 'customer');
                            $existingPaymentMethod = \App\Models\PaymentMethod::query()
                                ->where('user_id', $order->user_id)
                                ->where('provider', 'stripe')
                                ->where('token', $paymentMethodId)
                                ->first();

                            if (!$existingPaymentMethod && $customerFromIntent) {
                                $this->paymentMethodService->ensureStripePaymentMethodSaved(
                                    (int) $order->user_id,
                                    $paymentMethodId
                                );
                            }
                        }
                    }

                    if ($order->orderable && $order->getTypeValue() === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, [
                            'status' => 'confirmed',
                            'payment_status' => 'paid',
                        ]);
                        $booking->refresh();
                        $this->bookingService->sendBookingConfirmation($booking);
                    }

                    if ($order->getTypeValue() === 'ecommerce') {
                        $this->orderService->sendOrderConfirmation($order);
                    }

                    $order->refresh();
                    $payment->refresh();

                    DB::commit();

                    SyncOrderToZohoJob::dispatch($order);

                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
                break;

            case 'payment_intent.payment_failed':
                $paymentUpdate['status'] = 'failed';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $order = $this->orderService->cancel($order, ['reason' => 'payment_failed']);
                    $order->refresh();
                    $this->orderService->cancelBookingForOrder($order);
                }
                break;

            case 'payment_intent.canceled':
                $paymentUpdate['status'] = 'cancelled';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $order = $this->orderService->cancel($order, ['reason' => 'canceled']);
                    $order->refresh();
                    $this->orderService->cancelBookingForOrder($order);
                }
                break;

            default:
                \Log::warning('[stripe][webhook] Unknown event type', ['event_type' => $type]);
                $paymentUpdate['status'] = 'pending';
                break;
        }

        if ($type !== 'payment_intent.succeeded') {
            $this->paymentRepo->update($payment, $paymentUpdate);
        }

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

        $currentTime = time();
        $requestTime = (int) $timestamp;
        $timeDifference = abs($currentTime - $requestTime);

        if ($timeDifference > 300) {
            \Log::warning('[stripe][webhook] Request timestamp too old, possible replay attack');
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
