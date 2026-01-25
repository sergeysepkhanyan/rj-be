<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Integrations\Stripe\StripeClient;
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
        \Log::info('[stripe][webhook] Webhook received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'has_signature' => $request->hasHeader('Stripe-Signature'),
        ]);

        $payload = $request->getContent();
        $sigHeader = (string) $request->header('Stripe-Signature');
        $secret = (string) config('stripe.webhook_secret');

        if (!$this->isValidSignature($payload, $sigHeader, $secret)) {
            \Log::warning('[stripe][webhook] Invalid signature', [
                'has_secret' => !empty($secret),
                'has_header' => !empty($sigHeader),
            ]);
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $event = json_decode($payload, true);
        $type = data_get($event, 'type');
        $object = data_get($event, 'data.object', []);
        $paymentIntentId = data_get($object, 'id');

        \Log::info('[stripe][webhook] Event parsed', [
            'event_type' => $type,
            'payment_intent_id' => $paymentIntentId,
            'object_status' => data_get($object, 'status'),
        ]);

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

        if (!$order) {
            \Log::error('[stripe][webhook] Order not found for payment', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntentId,
                'order_id' => $payment->order_id,
            ]);
            return response()->json(['ok' => true]);
        }

        $paymentUpdate = ['raw' => $object];

        \Log::info('[stripe][webhook] Processing event', [
            'event_type' => $type,
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'current_payment_status' => $payment->status,
            'current_order_status' => $order->status,
        ]);

        switch ($type) {
            case 'payment_intent.succeeded':
                \Log::info('[stripe][webhook] Processing payment_intent.succeeded', [
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

                    \Log::info('[stripe][webhook] Payment status updated', [
                        'payment_id' => $payment->id,
                        'previous_status' => $previousPaymentStatus,
                        'new_status' => $payment->status,
                    ]);
                    $wasAlreadyPaid = $previousOrderStatus === OrderStatus::Paid->value;

                    \Log::info('[stripe][webhook] Marking order as paid', [
                        'order_id' => $order->id,
                        'previous_order_status' => $previousOrderStatus,
                        'order_type' => $order->type,
                        'was_already_paid' => $wasAlreadyPaid,
                    ]);

                    // Update order status to paid (this also decreases product quantities for ecommerce)
                    $order = $this->orderService->markPaid($order, ['stripe_payment_intent_id' => $paymentIntentId]);
                    $order->refresh(); // Ensure we have the latest status

                    \Log::info('[stripe][webhook] Order status updated', [
                        'order_id' => $order->id,
                        'previous_status' => $previousOrderStatus,
                        'new_status' => $order->status,
                        'status_changed' => $previousOrderStatus !== $order->status,
                    ]);

                    // Auto-save payment method for logged-in users (if not already saved)
                    // NOTE: This is non-critical - errors here should not prevent email sending
                    if ($order->user_id) {
                        $paymentMethodId = (string) data_get($object, 'payment_method');

                        // Check if payment method exists and if it was already attached to a customer
                        // If it was used in the payment intent without being attached, we can't attach it now
                        if ($paymentMethodId) {
                            try {
                                // Check the payment intent's payment_method to see if it's already attached
                                $customerFromIntent = data_get($object, 'customer');

                                // CRITICAL: If payment method was used in a payment intent, even WITH a customer,
                                // if it wasn't attached BEFORE the payment intent was created, Stripe marks it as
                                // "previously used" and we can't attach it. We need to check the payment method's
                                // current status BEFORE attempting attachment.

                                // First, check if payment method is already saved locally (skip if exists)
                                $existingPaymentMethod = \App\Models\PaymentMethod::query()
                                    ->where('user_id', $order->user_id)
                                    ->where('provider', 'stripe')
                                    ->where('token', $paymentMethodId)
                                    ->first();

                                if ($existingPaymentMethod) {
                                    \Log::info('[stripe][webhook] Payment method already saved locally, skipping', [
                                        'order_id' => $order->id,
                                        'user_id' => $order->user_id,
                                        'payment_method_id' => $paymentMethodId,
                                    ]);
                                } elseif (!$customerFromIntent) {
                                    // If payment method was used without a customer, Stripe won't let us attach it
                                    // Skip saving in this case to avoid the error
                                    \Log::info('[stripe][webhook] Payment method used without customer, skipping save', [
                                        'order_id' => $order->id,
                                        'user_id' => $order->user_id,
                                        'payment_method_id' => $paymentMethodId,
                                    ]);
                                } else {
                                    // Payment method was used with a customer - check if it's already attached
                                    // before attempting to save (which will try to attach)
                                    try {
                                        $pmCheck = $this->stripeClient->retrievePaymentMethod($paymentMethodId);
                                        $isAlreadyAttached = isset($pmCheck['customer']) && $pmCheck['customer'] === $customerFromIntent;

                                        if ($isAlreadyAttached) {
                                            // Already attached - safe to save locally
                                            $this->paymentMethodService->ensureStripePaymentMethodSaved(
                                                (int) $order->user_id,
                                                $paymentMethodId
                                            );
                                            \Log::info('[stripe][webhook] Payment method saved successfully (already attached)', [
                                                'user_id' => $order->user_id,
                                            ]);
                                        } else {
                                            // Not attached - might have been used without attachment
                                            // Try to save, but expect "previously used" error
                                            try {
                                                $this->paymentMethodService->ensureStripePaymentMethodSaved(
                                                    (int) $order->user_id,
                                                    $paymentMethodId
                                                );
                                                \Log::info('[stripe][webhook] Payment method saved successfully', [
                                                    'user_id' => $order->user_id,
                                                ]);
                                            } catch (\Exception $e) {
                                                // Check if error is about "previously used" - this is expected
                                                $errorMessage = strtolower($e->getMessage());
                                                $isPreviouslyUsedError = (
                                                    stripos($errorMessage, 'previously used') !== false ||
                                                    stripos($errorMessage, 'detach') !== false ||
                                                    stripos($errorMessage, 'cannot be reused') !== false
                                                );

                                                if ($isPreviouslyUsedError) {
                                                    \Log::info('[stripe][webhook] Payment method previously used, skipping save (expected - payment method was used without attachment)', [
                                                        'user_id' => $order->user_id,
                                                        'order_id' => $order->id,
                                                        'payment_method_id' => $paymentMethodId,
                                                        'customer_from_intent' => $customerFromIntent,
                                                    ]);
                                                } else {
                                                    \Log::warning('[stripe][webhook] Failed to save payment method (non-critical, continuing)', [
                                                        'error' => $e->getMessage(),
                                                        'user_id' => $order->user_id,
                                                        'order_id' => $order->id,
                                                    ]);
                                                }
                                            }
                                        }
                                    } catch (\Exception $checkException) {
                                        // If we can't check the payment method status, try saving anyway
                                        // (the save method will handle errors)
                                        try {
                                            $this->paymentMethodService->ensureStripePaymentMethodSaved(
                                                (int) $order->user_id,
                                                $paymentMethodId
                                            );
                                        } catch (\Exception $e) {
                                            $errorMessage = strtolower($e->getMessage());
                                            $isPreviouslyUsedError = (
                                                stripos($errorMessage, 'previously used') !== false ||
                                                stripos($errorMessage, 'detach') !== false ||
                                                stripos($errorMessage, 'cannot be reused') !== false
                                            );

                                            if ($isPreviouslyUsedError) {
                                                \Log::info('[stripe][webhook] Payment method previously used, skipping save (expected)', [
                                                    'user_id' => $order->user_id,
                                                    'order_id' => $order->id,
                                                    'payment_method_id' => $paymentMethodId,
                                                ]);
                                            } else {
                                                \Log::warning('[stripe][webhook] Failed to save payment method (non-critical, continuing)', [
                                                    'error' => $e->getMessage(),
                                                    'user_id' => $order->user_id,
                                                    'order_id' => $order->id,
                                                ]);
                                            }
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                // Catch any unexpected errors and log, but continue
                                \Log::warning('[stripe][webhook] Unexpected error in payment method save (non-critical, continuing)', [
                                    'error' => $e->getMessage(),
                                    'user_id' => $order->user_id,
                                    'order_id' => $order->id,
                                ]);
                            }
                        }
                    }

                    \Log::info('[stripe][webhook] ===== REACHED EMAIL SENDING SECTION =====', [
                        'order_id' => $order->id,
                        'order_type' => $order->type,
                        'order_type_value' => $order->type instanceof \BackedEnum ? $order->type->value : $order->type,
                        'order_type_string' => (string) $order->type,
                        'has_orderable' => !is_null($order->orderable),
                        'orderable_type' => $order->orderable_type ?? null,
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

                        \Log::info('[stripe][webhook] Booking status updated', [
                            'booking_id' => $booking->id,
                            'previous_status' => $previousBookingStatus,
                            'new_status' => $booking->status,
                            'previous_payment_status' => $previousPaymentStatus,
                            'new_payment_status' => $booking->payment_status,
                        ]);

                        // Send booking confirmation email after payment success (same as ecommerce)
                        \Log::info('[stripe][webhook] Sending booking confirmation email', [
                            'booking_id' => $booking->id,
                        ]);
                        try {
                            $this->bookingService->sendBookingConfirmation($booking);
                            \Log::info('[stripe][webhook] Booking confirmation email sent', [
                                'booking_id' => $booking->id,
                            ]);
                        } catch (\Exception $e) {
                            \Log::warning('[stripe][webhook] Failed to send booking confirmation email', [
                                'error' => $e->getMessage(),
                                'booking_id' => $booking->id,
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }

                    // Send order confirmation email for ecommerce orders
                    // Log order type check for debugging - MUST APPEAR BEFORE EMAIL SENDING
                    \Log::info('[stripe][webhook] ===== CHECKING ORDER TYPE FOR EMAIL =====', [
                        'order_id' => $order->id,
                        'order_type' => $order->type,
                        'order_type_string' => (string) $order->type,
                        'order_type_trimmed' => trim((string) $order->type),
                        'order_type_raw' => gettype($order->type),
                        'has_orderable' => !is_null($order->orderable),
                        'orderable_type' => $order->orderable_type ?? null,
                        'is_booking' => ($order->orderable && $order->type === 'booking'),
                        'is_ecommerce_strict' => ($order->type === 'ecommerce'),
                        'is_ecommerce_string' => ((string) $order->type === 'ecommerce'),
                        'is_ecommerce_trimmed' => (trim((string) $order->type) === 'ecommerce'),
                    ]);

                    // Use string comparison to handle any type casting issues
                    // Also trim to handle any whitespace issues
                    $orderTypeString = trim((string) $order->type);
                    \Log::info('[stripe][webhook] Final order type check', [
                        'order_id' => $order->id,
                        'orderTypeString' => $orderTypeString,
                        'comparison_result' => ($orderTypeString === 'ecommerce'),
                        'order_type_raw' => $order->type,
                        'order_type_gettype' => gettype($order->type),
                    ]);

                    // Send email for ecommerce orders
                    // Use multiple checks to ensure we catch ecommerce orders
                    $isEcommerce = (
                        $orderTypeString === 'ecommerce' ||
                        $order->type === 'ecommerce' ||
                        (is_string($order->type) && trim($order->type) === 'ecommerce') ||
                        ($order->type instanceof \BackedEnum && $order->type->value === 'ecommerce')
                    );

                    \Log::info('[stripe][webhook] Ecommerce check result', [
                        'order_id' => $order->id,
                        'is_ecommerce' => $isEcommerce,
                        'order_type' => $order->type,
                        'order_type_string' => $orderTypeString,
                    ]);

                    if ($isEcommerce) {
                        \Log::info('[stripe][webhook] Sending ecommerce order confirmation email', [
                            'order_id' => $order->id,
                        ]);
                        try {
                            $this->orderService->sendOrderConfirmation($order);
                            \Log::info('[stripe][webhook] Ecommerce order confirmation email sent successfully', [
                                'order_id' => $order->id,
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('[stripe][webhook] Failed to send order confirmation email', [
                                'error' => $e->getMessage(),
                                'order_id' => $order->id,
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    } else {
                        \Log::warning('[stripe][webhook] Order type does not match ecommerce, skipping email', [
                            'order_id' => $order->id,
                            'order_type' => $order->type,
                            'order_type_value' => $order->type instanceof \BackedEnum ? $order->type->value : $order->type,
                            'type_as_string' => (string) $order->type,
                            'type_trimmed' => trim((string) $order->type),
                            'is_booking' => ($order->orderable && $order->type === 'booking'),
                        ]);
                    }

                    // Refresh order to get latest status after all updates
                    $order->refresh();
                    $payment->refresh();

                    \DB::commit();

                    // Verify final statuses
                    $finalOrderStatus = $order->status;
                    $finalPaymentStatus = $payment->status;
                    $statusUpdateSuccess = ($finalOrderStatus === OrderStatus::Paid->value) && ($finalPaymentStatus === 'paid');

                    \Log::info('[stripe][webhook] Successfully processed payment', [
                        'payment_id' => $payment->id,
                        'payment_status_before' => $previousPaymentStatus,
                        'payment_status_after' => $finalPaymentStatus,
                        'order_id' => $order->id,
                        'order_status_before' => $previousOrderStatus,
                        'order_status_after' => $finalOrderStatus,
                        'order_type' => $order->type,
                        'order_type_string' => (string) $order->type,
                        'order_type_trimmed' => trim((string) $order->type),
                        'is_ecommerce' => ((string) $order->type === 'ecommerce'),
                        'status_update_success' => $statusUpdateSuccess,
                        'email_sent' => $order->type === 'ecommerce' || ($order->orderable && $order->type === 'booking'),
                        'quantities_decreased' => $order->type === 'ecommerce' && ($previousOrderStatus !== OrderStatus::Paid->value),
                    ]);

                    if (!$statusUpdateSuccess) {
                        \Log::error('[stripe][webhook] Status update verification failed', [
                            'payment_id' => $payment->id,
                            'order_id' => $order->id,
                            'expected_order_status' => OrderStatus::Paid->value,
                            'actual_order_status' => $finalOrderStatus,
                            'expected_payment_status' => 'paid',
                            'actual_payment_status' => $finalPaymentStatus,
                        ]);
                    }

                } catch (\Exception $e) {
                    \DB::rollBack();
                    \Log::error('[stripe][webhook] Error processing payment', [
                        'payment_id' => $payment->id,
                        'payment_intent_id' => $paymentIntentId,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
                break;

            case 'payment_intent.payment_failed':
                \Log::info('[stripe][webhook] Processing payment_intent.payment_failed', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
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
                \Log::info('[stripe][webhook] Processing payment_intent.canceled', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
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
                \Log::warning('[stripe][webhook] Unknown event type', [
                    'event_type' => $type,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ]);
                $paymentUpdate['status'] = 'pending';
                break;
        }

        \Log::info('[stripe][webhook] Event processing completed', [
            'event_type' => $type,
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'payment_status_to_update' => $paymentUpdate['status'] ?? 'unknown',
        ]);

        // Only update payment if not already updated in the success case
        if ($type !== 'payment_intent.succeeded') {
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
        }

        \Log::info('[stripe][webhook] Webhook processing completed successfully', [
            'event_type' => $type,
            'payment_id' => $payment->id,
            'order_id' => $order?->id,
            'final_payment_status' => $payment->status,
            'final_order_status' => $order?->status,
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

        // Prevent replay attacks: reject requests older than 5 minutes
        $currentTime = time();
        $requestTime = (int) $timestamp;
        $timeDifference = abs($currentTime - $requestTime);

        if ($timeDifference > 300) { // 5 minutes
            \Log::warning('[stripe][webhook] Request timestamp too old, possible replay attack', [
                'timestamp' => $timestamp,
                'current_time' => $currentTime,
                'difference' => $timeDifference,
            ]);
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
