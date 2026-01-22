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
        
        if (!$order) {
            \Log::error('[tabby][webhook] Order not found for payment', [
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
                    \DB::beginTransaction();
                    
                    // Update payment status FIRST to ensure it's recorded
                    $this->paymentRepo->update($payment, $paymentUpdate);
                    
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
                        
                        // Send booking confirmation email after payment success (same as ecommerce)
                        try {
                            $this->bookingService->sendBookingConfirmation($booking);
                        } catch (\Exception $e) {
                            \Log::warning('[tabby][webhook] Failed to send booking confirmation email', [
                                'error' => $e->getMessage(),
                                'booking_id' => $booking->id,
                            ]);
                        }
                    } elseif ($order->type === 'ecommerce') {
                        // Send order confirmation email for ecommerce orders
                        try {
                            $this->orderService->sendOrderConfirmation($order);
                        } catch (\Exception $e) {
                            \Log::warning('[tabby][webhook] Failed to send order confirmation email', [
                                'error' => $e->getMessage(),
                                'order_id' => $order->id,
                            ]);
                        }
                    }
                    
                    \DB::commit();
                    
                    \Log::info('[tabby][webhook] Successfully processed payment', [
                        'payment_id' => $payment->id,
                        'order_id' => $order->id,
                        'order_status' => $order->status,
                    ]);
                    
                } catch (\Exception $e) {
                    \DB::rollBack();
                    \Log::error('[tabby][webhook] Error processing payment', [
                        'payment_id' => $payment->id,
                        'tabby_payment_id' => $tabbyPaymentId,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
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

        // Only update payment if not already updated in the CLOSED case
        if ($status !== 'CLOSED') {
            $this->paymentRepo->update($payment, $paymentUpdate);
        }

        return response()->json(['ok' => true]);
    }
}
