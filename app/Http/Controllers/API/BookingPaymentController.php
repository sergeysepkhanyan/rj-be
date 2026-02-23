<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Integrations\Stripe\StripeClient;
use App\Models\Booking;
use App\Services\ApiResponse;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            // Payment succeeded - update booking and order
            DB::transaction(function () use ($booking) {
                $order = $booking->order;

                if ($order) {
                    $order->update(['status' => 'paid']);

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
}
