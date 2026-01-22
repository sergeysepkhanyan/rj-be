<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Integrations\Tabby\TabbyClient;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\OrderService;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                    $previousOrderStatus = $order->status;
                    \Log::info('[tabby][webhook] Marking order as paid', [
                        'order_id' => $order->id,
                        'previous_order_status' => $previousOrderStatus,
                        'order_type' => $order->type,
                    ]);
                    
                    // Update order status to paid
                    $order = $this->orderService->markPaid($order, ['tabby_payment_id' => $tabbyPaymentId]);
                    $order->refresh(); // Ensure we have the latest status
                    
                    \Log::info('[tabby][webhook] Order status updated', [
                        'order_id' => $order->id,
                        'previous_status' => $previousOrderStatus,
                        'new_status' => $order->status,
                        'status_changed' => $previousOrderStatus !== $order->status,
                    ]);

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
                        
                        \Log::info('[tabby][webhook] Booking status updated', [
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

            case 'REJECTED':
                $paymentUpdate['status'] = 'failed';
                $paymentUpdate['failed_at'] = now();
                if ($order) {
                    $previousOrderStatus = $order->status;
                    $order = $this->orderService->cancel($order, ['reason' => $status]);
                    $order->refresh();
                    
                    \Log::info('[tabby][webhook] Order cancelled (rejected)', [
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
                        
                        \Log::info('[tabby][webhook] Booking cancelled (rejected)', [
                            'booking_id' => $booking->id,
                            'previous_status' => $previousBookingStatus,
                            'new_status' => $booking->status,
                        ]);
                    }
                }
                break;

            case 'EXPIRED':
                $paymentUpdate['status'] = 'expired';
                $paymentUpdate['expired_at'] = now();
                if ($order) {
                    $previousOrderStatus = $order->status;
                    $order = $this->orderService->cancel($order, ['reason' => $status]);
                    $order->refresh();
                    
                    \Log::info('[tabby][webhook] Order cancelled (expired)', [
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
                        
                        \Log::info('[tabby][webhook] Booking cancelled (expired)', [
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

        $this->paymentRepo->update($payment, $paymentUpdate);

        return response()->json(['ok' => true]);
    }
}
