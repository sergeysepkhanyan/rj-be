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
        Log::info('[tabby][webhook] Webhook received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
        
        $hn = config('tabby.webhook.header_name');
        $expected = config('tabby.webhook.header_value');
        $actual = $request->header($hn);

        if (!$actual || !hash_equals((string)$expected, (string)$actual)) {
            Log::warning('[tabby][webhook] Invalid signature', [
                'has_secret' => !empty($expected),
                'has_header' => !empty($actual),
            ]);
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $tabbyPaymentId = $request->input('payment.id') ?? $request->input('payment_id');
        if (!$tabbyPaymentId) {
            Log::warning('[tabby][webhook] Missing payment id');
            return response()->json(['message' => 'Missing payment id'], Response::HTTP_BAD_REQUEST);
        }

        $remote = $this->tabbyClient->retrievePayment($tabbyPaymentId);
        $status = strtoupper((string) data_get($remote, 'status', 'UNKNOWN')); // AUTHORIZED/CLOSED/REJECTED/EXPIRED...
        
        Log::info('[tabby][webhook] Event parsed', [
            'tabby_payment_id' => $tabbyPaymentId,
            'status' => $status,
        ]);

        $payment = $this->paymentRepo->findByProviderExternalId('tabby', $tabbyPaymentId);
        if (!$payment) {
            Log::warning('[tabby][webhook] Payment not found', [
                'tabby_payment_id' => $tabbyPaymentId,
                'status' => $status,
            ]);
            return response()->json(['ok' => true]);
        }

        Log::info('[tabby][webhook] Payment found', [
            'payment_id' => $payment->id,
            'tabby_payment_id' => $tabbyPaymentId,
            'current_status' => $payment->status,
            'remote_status' => $status,
        ]);

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
        
        Log::info('[tabby][webhook] Processing event', [
            'status' => $status,
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'current_payment_status' => $payment->status,
            'current_order_status' => $order->status,
        ]);

        switch ($status) {
            case 'AUTHORIZED':
                $paymentUpdate['status'] = 'authorized';
                $paymentUpdate['authorized_at'] = now();
                $this->paymentRepo->update($payment, $paymentUpdate);
                break;

            case 'CLOSED':
                Log::info('[tabby][webhook] Processing CLOSED status', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
                $paymentUpdate['status'] = 'paid';
                $paymentUpdate['paid_at'] = now();
                
                try {
                    \DB::beginTransaction();
                    
                    $previousOrderStatus = $order->status;
                    $previousPaymentStatus = $payment->status;
                    
                    // Update payment status FIRST to ensure it's recorded
                    $this->paymentRepo->update($payment, $paymentUpdate);
                    $payment->refresh();
                    
                    Log::info('[tabby][webhook] Payment status updated', [
                        'payment_id' => $payment->id,
                        'previous_status' => $previousPaymentStatus,
                        'new_status' => $payment->status,
                    ]);
                    
                    $wasAlreadyPaid = $previousOrderStatus === \App\Enums\OrderStatus::Paid->value;
                    
                    Log::info('[tabby][webhook] Marking order as paid', [
                        'order_id' => $order->id,
                        'previous_order_status' => $previousOrderStatus,
                        'order_type' => $order->type,
                        'was_already_paid' => $wasAlreadyPaid,
                    ]);
                    
                    // Update order status to paid (this also decreases product quantities for ecommerce)
                    $order = $this->orderService->markPaid($order, ['tabby_payment_id' => $tabbyPaymentId]);
                    $order->refresh(); // Ensure we have the latest status
                    
                    Log::info('[tabby][webhook] Order status updated', [
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
                        
                        Log::info('[tabby][webhook] Booking status updated', [
                            'booking_id' => $booking->id,
                            'previous_status' => $previousBookingStatus,
                            'new_status' => $booking->status,
                            'previous_payment_status' => $previousPaymentStatus,
                            'new_payment_status' => $booking->payment_status,
                        ]);
                        
                        // Send booking confirmation email after payment success (same as ecommerce)
                        try {
                            $this->bookingService->sendBookingConfirmation($booking);
                            Log::info('[tabby][webhook] Booking confirmation email sent', [
                                'booking_id' => $booking->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('[tabby][webhook] Failed to send booking confirmation email', [
                                'error' => $e->getMessage(),
                                'booking_id' => $booking->id,
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    } elseif ($order->type === 'ecommerce') {
                        // Send order confirmation email for ecommerce orders
                        Log::info('[tabby][webhook] Sending ecommerce order confirmation email', [
                            'order_id' => $order->id,
                        ]);
                        try {
                            $this->orderService->sendOrderConfirmation($order);
                            Log::info('[tabby][webhook] Ecommerce order confirmation email sent', [
                                'order_id' => $order->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('[tabby][webhook] Failed to send order confirmation email', [
                                'error' => $e->getMessage(),
                                'order_id' => $order->id,
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                    
                    // Refresh order to get latest status after all updates
                    $order->refresh();
                    $payment->refresh();
                    
                    \DB::commit();
                    
                    // Verify final statuses
                    $finalOrderStatus = $order->status;
                    $finalPaymentStatus = $payment->status;
                    $statusUpdateSuccess = ($finalOrderStatus === \App\Enums\OrderStatus::Paid->value) && ($finalPaymentStatus === 'paid');
                    
                    Log::info('[tabby][webhook] Successfully processed payment', [
                        'payment_id' => $payment->id,
                        'payment_status_before' => $previousPaymentStatus,
                        'payment_status_after' => $finalPaymentStatus,
                        'order_id' => $order->id,
                        'order_status_before' => $previousOrderStatus,
                        'order_status_after' => $finalOrderStatus,
                        'order_type' => $order->type,
                        'status_update_success' => $statusUpdateSuccess,
                        'email_sent' => $order->type === 'ecommerce' || ($order->orderable && $order->type === 'booking'),
                        'quantities_decreased' => $order->type === 'ecommerce' && ($previousOrderStatus !== \App\Enums\OrderStatus::Paid->value),
                    ]);
                    
                    if (!$statusUpdateSuccess) {
                        Log::error('[tabby][webhook] Status update verification failed', [
                            'payment_id' => $payment->id,
                            'order_id' => $order->id,
                            'expected_order_status' => \App\Enums\OrderStatus::Paid->value,
                            'actual_order_status' => $finalOrderStatus,
                            'expected_payment_status' => 'paid',
                            'actual_payment_status' => $finalPaymentStatus,
                        ]);
                    }
                    
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
                Log::warning('[tabby][webhook] Unknown status', [
                    'status' => $status,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
                $paymentUpdate['status'] = 'pending';
                break;
        }
        
        Log::info('[tabby][webhook] Event processing completed', [
            'status' => $status,
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'payment_status_to_update' => $paymentUpdate['status'] ?? 'unknown',
        ]);

        // Only update payment if not already updated in the CLOSED case
        if ($status !== 'CLOSED') {
            $this->paymentRepo->update($payment, $paymentUpdate);
            $payment->refresh();
        }

        Log::info('[tabby][webhook] Webhook processing completed successfully', [
            'status' => $status,
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'final_payment_status' => $payment->status,
            'final_order_status' => $order->status,
        ]);

        return response()->json(['ok' => true]);
    }
}
