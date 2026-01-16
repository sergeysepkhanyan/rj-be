<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Integrations\Tabby\TabbyClient;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\OrderService;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TabbyWebhookController extends Controller
{
    public function __construct(
        protected TabbyClient $tabbyClient,
        protected PaymentRepositoryInterface $paymentRepo,
        protected OrderService $orderService,
        protected BookingRepositoryInterface $bookingRepo,
    ) {}

    public function handle(Request $request)
    {
        $hn = config('tabby.webhook.header_name');
        $expected = config('tabby.webhook.header_value');
        $actual = $request->header($hn);

        if (!$actual || !hash_equals((string)$expected, (string)$actual)) {
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $tabbyPaymentId = $request->input('payment.id') ?? $request->input('payment_id');
        if (!$tabbyPaymentId) {
            return response()->json(['message' => 'Missing payment id'], Response::HTTP_BAD_REQUEST);
        }

        $remote = $this->tabbyClient->retrievePayment($tabbyPaymentId);
        $status = strtoupper((string) data_get($remote, 'status', 'UNKNOWN')); // AUTHORIZED/CLOSED/REJECTED/EXPIRED...

        $payment = $this->paymentRepo->findByProviderExternalId('tabby', $tabbyPaymentId);
        if (!$payment) {
            return response()->json(['ok' => true]);
        }

        $paymentUpdate = ['raw' => $remote];
        $order = $payment->order()->with('orderable')->first();

        switch ($status) {
            case 'AUTHORIZED':
                $paymentUpdate['status'] = 'authorized';
                $paymentUpdate['authorized_at'] = now();
                break;

            case 'CLOSED':
                $paymentUpdate['status'] = 'paid';
                $paymentUpdate['paid_at'] = now();
                if ($order) {
                    $this->orderService->markPaid($order, ['tabby_payment_id' => $tabbyPaymentId]);
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, ['status' => 'confirmed']);
                    }
                }
                break;

            case 'REJECTED':
                $paymentUpdate['status'] = 'failed';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $this->orderService->cancel($order, ['reason' => $status]);
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, ['status' => 'cancelled']);
                    }
                }
                break;

            case 'EXPIRED':
                $paymentUpdate['status'] = 'expired';
                $paymentUpdate['expired_at'] = now();
                if ($order) {
                    $this->orderService->cancel($order, ['reason' => $status]);
                    if ($order->orderable && $order->type === 'booking') {
                        $booking = $order->orderable;
                        $this->bookingRepo->update($booking, ['status' => 'cancelled']);
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
}
