<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Integrations\Tabby\TabbyClient;
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
        $hn = config('tabby.webhook.header_name');
        $expected = config('tabby.webhook.header_value');
        $actual = $request->header($hn);

        if (!$actual || !hash_equals((string) $expected, (string) $actual)) {
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

                    $this->paymentRepo->update($payment, $paymentUpdate);
                    $payment->refresh();

                    $order = $this->orderService->markPaid($order, ['tabby_payment_id' => $tabbyPaymentId]);
                    $order->refresh();

                    if ($order->orderable && $order->getTypeValue() === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, [
                            'status' => 'confirmed',
                            'payment_status' => 'paid',
                        ]);
                        $booking->refresh();
                        $this->bookingService->sendBookingConfirmation($booking);
                    } elseif ($order->getTypeValue() === 'ecommerce') {
                        $this->orderService->sendOrderConfirmation($order);
                    }

                    $order->refresh();
                    $payment->refresh();

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
                break;

            case 'REJECTED':
                $paymentUpdate['status'] = 'failed';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $order = $this->orderService->cancel($order, ['reason' => $status]);
                    $order->refresh();
                    $this->orderService->cancelBookingForOrder($order);
                }
                break;

            case 'EXPIRED':
                $paymentUpdate['status'] = 'expired';
                $paymentUpdate['expired_at'] = now();
                if ($order) {
                    $order = $this->orderService->cancel($order, ['reason' => $status]);
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
}
