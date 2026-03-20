<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Integrations\Tabby\TabbyClient;
use App\Jobs\Zoho\SyncOrderToZohoJob;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TabbyWebhookController extends Controller
{
    public function __construct(
        protected TabbyClient $tabbyClient,
        protected PaymentRepositoryInterface $paymentRepo,
        protected OrderService $orderService,
        protected BookingRepositoryInterface $bookingRepo,
        protected BookingService $bookingService,
    ) {}

    public function handle(Request $request)
    {
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('[tabby][webhook] Invalid signature');
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $tabbyPaymentId = $request->input('payment.id') ?? $request->input('payment_id');
        if (!$tabbyPaymentId) {
            Log::warning('[tabby][webhook] Missing payment id');
            return response()->json(['message' => 'Missing payment id'], Response::HTTP_BAD_REQUEST);
        }

        $remote = $this->tabbyClient->retrievePayment($tabbyPaymentId);
        $status = strtoupper((string) data_get($remote, 'status', 'UNKNOWN'));

        $payment = $this->paymentRepo->findByProviderExternalId('tabby', $tabbyPaymentId);
        if (!$payment) {
            Log::warning('[tabby][webhook] Payment not found', ['tabby_payment_id' => $tabbyPaymentId, 'status' => $status]);
            return response()->json(['ok' => true]);
        }

        $paymentUpdate = ['raw' => $remote];
        $order = $payment->order()->with('orderable')->first();

        if (!$order) {
            Log::error('[tabby][webhook] Order not found for payment', [
                'payment_id' => $payment->id,
                'tabby_payment_id' => $tabbyPaymentId,
                'order_id' => $payment->order_id,
            ]);
            return response()->json(['ok' => true]);
        }

        switch ($status) {
            case 'AUTHORIZED':
                $paymentUpdate['status'] = 'authorized';
                $paymentUpdate['authorized_at'] = now();
                $this->paymentRepo->update($payment, $paymentUpdate);
                break;

            case 'CLOSED':
                $paymentUpdate['status'] = 'paid';
                $paymentUpdate['paid_at'] = now();

                try {
                    DB::beginTransaction();

                    $wasAlreadyPaid = $order->status === \App\Enums\OrderStatus::Paid->value;

                    $this->paymentRepo->update($payment, $paymentUpdate);
                    $payment->refresh();

                    try {
                        $order = $this->orderService->markPaid($order, ['tabby_payment_id' => $tabbyPaymentId]);
                    } catch (\InvalidArgumentException $e) {
                        Log::warning('[tabby][webhook] Cannot mark order paid - invalid transition', [
                            'order_id' => $order->id, 'status' => $order->status, 'error' => $e->getMessage(),
                        ]);
                        DB::commit();
                        return response()->json(['ok' => true]);
                    }
                    $order->refresh();

                    if (!$wasAlreadyPaid) {
                        if ($order->orderable && $order->getTypeValue() === 'booking') {
                            $booking = $order->orderable;

                            // Re-validate slot availability before confirming
                            if (!$this->bookingService->isSlotAvailable($booking)) {
                                Log::error('[tabby][webhook] Booking slot no longer available', [
                                    'booking_id' => $booking->id, 'order_id' => $order->id,
                                ]);
                                $this->bookingRepo->update($booking, [
                                    'status' => 'cancelled',
                                    'payment_status' => 'refunded',
                                ]);
                                // Note: Tabby refund should be handled manually by admin
                                DB::commit();
                                return response()->json(['ok' => true]);
                            }

                            $this->bookingRepo->update($booking, [
                                'status' => 'confirmed',
                                'payment_status' => 'paid',
                            ]);
                            $booking->refresh();
                            $this->bookingService->sendBookingConfirmation($booking);
                        } elseif ($order->getTypeValue() === 'ecommerce') {
                            $this->orderService->sendOrderConfirmation($order);
                        }
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

            case 'REJECTED':
                $paymentUpdate['status'] = 'failed';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    try {
                        $order = $this->orderService->cancel($order, ['reason' => $status]);
                    } catch (\InvalidArgumentException $e) {
                        Log::warning('[tabby][webhook] Cannot cancel order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                    }
                    $order->refresh();
                    $this->orderService->cancelBookingForOrder($order);
                }
                break;

            case 'EXPIRED':
                $paymentUpdate['status'] = 'expired';
                $paymentUpdate['expired_at'] = now();
                if ($order) {
                    try {
                        $order = $this->orderService->cancel($order, ['reason' => $status]);
                    } catch (\InvalidArgumentException $e) {
                        Log::warning('[tabby][webhook] Cannot cancel order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                    }
                    $order->refresh();
                    $this->orderService->cancelBookingForOrder($order);
                }
                break;

            default:
                Log::warning('[tabby][webhook] Unknown status', ['status' => $status, 'payment_id' => $payment->id]);
                $paymentUpdate['status'] = 'pending';
                break;
        }

        if ($status !== 'CLOSED') {
            $this->paymentRepo->update($payment, $paymentUpdate);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Verify webhook signature with HMAC support (backward compatible).
     * If hmac_secret is configured, validate HMAC-SHA256 of payload.
     * Otherwise, fall back to static header comparison.
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $hmacSecret = config('tabby.webhook.hmac_secret');

        if ($hmacSecret) {
            $signatureHeader = $request->header('X-Tabby-Signature');
            if (!$signatureHeader) {
                return false;
            }

            $payload = $request->getContent();
            $computed = hash_hmac('sha256', $payload, $hmacSecret);

            if (!hash_equals($computed, (string) $signatureHeader)) {
                return false;
            }

            // Replay protection: check timestamp if provided
            $timestamp = $request->header('X-Tabby-Timestamp');
            if ($timestamp) {
                $requestTime = (int) $timestamp;
                $tolerance = (int) config('tabby.webhook.timestamp_tolerance', 300);
                if (abs(time() - $requestTime) > $tolerance) {
                    Log::warning('[tabby][webhook] Request timestamp too old, possible replay attack');
                    return false;
                }
            }

            return true;
        }

        // Fallback: static header comparison (legacy)
        Log::info('[tabby][webhook] Using legacy static header verification — consider configuring TABBY_WEBHOOK_HMAC_SECRET');
        $hn = config('tabby.webhook.header_name');
        $expected = config('tabby.webhook.header_value');
        $actual = $request->header($hn);

        return $actual && hash_equals((string) $expected, (string) $actual);
    }
}
