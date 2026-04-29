<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Integrations\Stripe\StripeClient;
use App\Mail\GiftCardBalanceDeductedMail;
use App\Models\Booking;
use App\Models\GiftCardPurchase;
use App\Models\GiftCardUsage;
use App\Services\ApiResponse;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BookingPaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected OrderService $orderService,
        protected BookingService $bookingService,
        protected StripeClient $stripeClient
    ) {}

    /**
     * Initiate payment for an existing unpaid booking (Pay Later -> Pay Now)
     */
    public function initiatePayment(Request $request, Booking $booking): JsonResponse
    {
        $user = auth()->user();

        // Validate booking ownership
        if (!$user?->isAdmin() && (int) $booking->user_id !== (int) $user?->id) {
            return ApiResponse::error(
                null,
                __('messages.booking.unauthorized'),
                403
            );
        }

        // Validate booking is in a valid state for payment
        if ($booking->status === 'cancelled') {
            return ApiResponse::error(
                ['status' => 'Booking has been cancelled'],
                __('messages.booking.cannot_pay_cancelled'),
                422
            );
        }

        if ($booking->status === 'completed') {
            return ApiResponse::error(
                ['status' => 'Booking has already been completed'],
                __('messages.booking.cannot_pay_completed'),
                422
            );
        }

        // Check payment status
        if ($booking->payment_status === 'paid') {
            return ApiResponse::error(
                ['paymentStatus' => 'Booking has already been paid'],
                __('messages.booking.already_paid'),
                422
            );
        }

        if ($booking->payment_status === 'refunded') {
            return ApiResponse::error(
                ['paymentStatus' => 'Booking payment has been refunded'],
                __('messages.booking.already_refunded'),
                422
            );
        }

        try {
            $result = DB::transaction(function () use ($booking) {
                // Ensure booking has an order, create if necessary
                $order = $booking->order;

                if (!$order) {
                    // Create order for booking
                    $order = $this->orderService->createForBooking($booking, 'pay_now');
                    $booking->refresh();
                    $order = $booking->order;
                }

                if (!$order) {
                    throw new \Exception('Failed to create order for booking');
                }

                // Update booking payment mode if it was pay_later
                if ($booking->payment_mode === 'pay_later') {
                    $booking->update(['payment_mode' => 'pay_now']);
                }

                // Create Stripe payment intent
                $paymentData = $this->paymentService->createPaymentForExistingBooking($booking, $order);

                return $paymentData;
            });

            return ApiResponse::success($result, __('messages.payment.intent_created'));
        } catch (\Throwable $e) {
            \Log::error('BookingPaymentController::initiatePayment error', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                null,
                __('messages.payment.failed_to_create_intent'),
                500
            );
        }
    }

    /**
     * Confirm payment status with Stripe and update booking
     * This is a fallback for when webhooks don't fire (e.g., local development)
     */
    public function confirmPayment(Request $request, Booking $booking): JsonResponse
    {
        $user = auth()->user();

        // Validate booking ownership
        if (!$user?->isAdmin() && (int) $booking->user_id !== (int) $user?->id) {
            return ApiResponse::error(
                null,
                __('messages.booking.unauthorized'),
                403
            );
        }

        $paymentIntentId = $request->input('payment_intent_id');

        if (!$paymentIntentId) {
            return ApiResponse::error(
                ['payment_intent_id' => 'Payment intent ID is required'],
                __('validation.required', ['attribute' => 'payment intent ID']),
                422
            );
        }

        // If already paid, return success
        if ($booking->payment_status === 'paid') {
            return ApiResponse::success([
                'booking_id' => $booking->id,
                'payment_status' => 'paid',
                'already_paid' => true,
            ], __('messages.booking.already_paid'));
        }

        try {
            // Verify payment with Stripe using custom client
            $paymentIntent = $this->stripeClient->retrievePaymentIntent($paymentIntentId);
            $status = data_get($paymentIntent, 'status');

            if ($status !== 'succeeded') {
                return ApiResponse::error(
                    ['stripe_status' => $status],
                    __('messages.payment.not_succeeded'),
                    422
                );
            }

            // Re-validate slot availability before confirming the booking
            if (!$this->bookingService->areSlotsStillAvailable($booking)) {
                $this->bookingService->cancelBookingDueToSlotConflict($booking);
                return ApiResponse::error(
                    ['slot' => 'The requested time slot is no longer available. The booking has been cancelled and a refund initiated.'],
                    __('messages.booking.slot_no_longer_available'),
                    409
                );
            }

            // Payment succeeded - update booking and order
            DB::transaction(function () use ($booking, $paymentIntentId) {
                $order = $booking->order;

                if ($order) {
                    // markPaid sets status='paid' AND paid_at=now() so the
                    // turnover dashboard can see this revenue. A raw
                    // ->update(['status'=>'paid']) leaves paid_at NULL and
                    // hides the order from getTodaysTurnover().
                    $this->orderService->markPaid($order, [
                        'stripe_payment_intent_id' => $paymentIntentId,
                    ]);

                    // Update payment record if exists
                    $payment = $order->payment;
                    if ($payment) {
                        $payment->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);
                    }
                }

                // Update booking status
                $booking->update([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                ]);

                // If batch booking, mark all bookings as paid
                if ($booking->batch_id) {
                    $this->bookingService->markBatchBookingsPaid($booking->batch_id);
                }

                // Send confirmation email
                $this->bookingService->sendBookingConfirmation($booking);
            });

            return ApiResponse::success([
                'booking_id' => $booking->id,
                'payment_status' => 'paid',
            ], __('messages.payment.confirmed'));

        } catch (\Illuminate\Http\Client\RequestException $e) {
            \Log::error('BookingPaymentController::confirmPayment Stripe error', [
                'booking_id' => $booking->id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                null,
                __('messages.payment.verification_failed'),
                500
            );
        } catch (\Throwable $e) {
            \Log::error('BookingPaymentController::confirmPayment error', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                null,
                __('messages.payment.confirmation_failed'),
                500
            );
        }
    }

    /**
     * Pay for a booking entirely with a gift card
     */
    public function payWithGiftCard(Request $request, Booking $booking): JsonResponse
    {
        $user = auth()->user();

        if (!$user?->isAdmin() && (int) $booking->user_id !== (int) $user?->id) {
            return ApiResponse::error(null, __('messages.booking.unauthorized'), 403);
        }

        if ($booking->payment_status === 'paid') {
            return ApiResponse::error(['paymentStatus' => 'Booking has already been paid'], __('messages.booking.already_paid'), 422);
        }

        $giftCardCode = $request->input('gift_card_code');
        if (!$giftCardCode) {
            return ApiResponse::error(['gift_card_code' => 'Gift card code is required'], __('validation.required', ['attribute' => 'gift card code']), 422);
        }

        try {
            $result = DB::transaction(function () use ($booking, $giftCardCode, $user) {
                $purchase = GiftCardPurchase::where('code', $giftCardCode)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (!$purchase || $purchase->isExpired() || $purchase->balance <= 0) {
                    throw new \Exception('Gift card is not valid or has no balance.');
                }

                $order = $booking->order;
                if (!$order) {
                    $order = $this->orderService->createForBooking($booking, 'pay_now');
                    $booking->refresh();
                    $order = $booking->order;
                }

                $total = (float) $order->amount;

                if ((float) $purchase->balance < $total) {
                    throw new \Exception('Gift card balance is insufficient to cover the full amount.');
                }

                $newBalance = max(0, (float) $purchase->balance - $total);
                $purchase->update([
                    'balance' => $newBalance,
                    ...($newBalance <= 0 ? ['status' => 'used'] : []),
                ]);

                GiftCardUsage::create([
                    'gift_card_purchase_id' => $purchase->id,
                    'amount_used' => $total,
                    'used_for_type' => 'booking',
                    'used_for_id' => $booking->id,
                    'used_for_name' => $booking->service?->name ?? 'Booking #' . $booking->id,
                    'used_for' => 'booking',
                    'notes' => 'Full payment via gift card at online checkout',
                    'verified_by' => $user?->id,
                ]);

                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'meta' => array_merge($order->meta ?? [], [
                        'gift_card_code' => $giftCardCode,
                        'gift_card_amount' => $total,
                    ]),
                ]);

                $booking->update([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'payment_mode' => 'pay_now',
                ]);

                if ($booking->batch_id) {
                    $this->bookingService->markBatchBookingsPaid($booking->batch_id);
                }

                $this->bookingService->sendBookingConfirmation($booking);

                if ($purchase->buyer_email) {
                    Mail::to($purchase->buyer_email)->queue(new GiftCardBalanceDeductedMail($purchase, $total));
                }

                return ['booking_id' => $booking->id, 'payment_status' => 'paid'];
            });

            return ApiResponse::success($result, __('messages.payment.confirmed'));
        } catch (\Throwable $e) {
            \Log::error('BookingPaymentController::payWithGiftCard error', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(null, $e->getMessage(), 422);
        }
    }

    /**
     * Apply a gift card to partially cover a booking payment (remaining paid via Stripe)
     */
    public function applyGiftCard(Request $request, Booking $booking): JsonResponse
    {
        $user = auth()->user();

        if (!$user?->isAdmin() && (int) $booking->user_id !== (int) $user?->id) {
            return ApiResponse::error(null, __('messages.booking.unauthorized'), 403);
        }

        if ($booking->payment_status === 'paid') {
            return ApiResponse::error(['paymentStatus' => 'Booking has already been paid'], __('messages.booking.already_paid'), 422);
        }

        $giftCardCode = $request->input('gift_card_code');
        if (!$giftCardCode) {
            return ApiResponse::error(['gift_card_code' => 'Gift card code is required'], __('validation.required', ['attribute' => 'gift card code']), 422);
        }

        try {
            $result = DB::transaction(function () use ($booking, $giftCardCode, $user) {
                $purchase = GiftCardPurchase::where('code', $giftCardCode)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (!$purchase || $purchase->isExpired() || $purchase->balance <= 0) {
                    throw new \Exception('Gift card is not valid or has no balance.');
                }

                $order = $booking->order;
                if (!$order) {
                    throw new \Exception('No order found for this booking.');
                }

                $total = (float) $order->amount;
                $giftCardAmountApplied = min((float) $purchase->balance, $total);
                $remainingAmount = $total - $giftCardAmountApplied;

                $newBalance = max(0, (float) $purchase->balance - $giftCardAmountApplied);
                $purchase->update([
                    'balance' => $newBalance,
                    ...($newBalance <= 0 ? ['status' => 'used'] : []),
                ]);

                GiftCardUsage::create([
                    'gift_card_purchase_id' => $purchase->id,
                    'amount_used' => $giftCardAmountApplied,
                    'used_for_type' => 'booking',
                    'used_for_id' => $booking->id,
                    'used_for_name' => $booking->service?->name ?? 'Booking #' . $booking->id,
                    'used_for' => 'booking',
                    'notes' => 'Partial payment via gift card at online checkout',
                    'verified_by' => $user?->id,
                ]);

                $order->update([
                    'amount' => $remainingAmount,
                    'meta' => array_merge($order->meta ?? [], [
                        'gift_card_code' => $giftCardCode,
                        'gift_card_amount' => $giftCardAmountApplied,
                        'original_amount' => $total,
                    ]),
                ]);

                // Update Stripe payment intent with new amount
                $payment = $order->latestPayment;
                if ($payment && $payment->external_id) {
                    $this->stripeClient->updatePaymentIntent($payment->external_id, [
                        'amount' => (int) round($remainingAmount * 100),
                    ]);
                }

                if ($purchase->buyer_email) {
                    Mail::to($purchase->buyer_email)->queue(new GiftCardBalanceDeductedMail($purchase, $giftCardAmountApplied));
                }

                return [
                    'gift_card_applied' => $giftCardAmountApplied,
                    'remaining_amount' => $remainingAmount,
                ];
            });

            return ApiResponse::success($result, 'Gift card applied successfully.');
        } catch (\Throwable $e) {
            \Log::error('BookingPaymentController::applyGiftCard error', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(null, $e->getMessage(), 422);
        }
    }
}
