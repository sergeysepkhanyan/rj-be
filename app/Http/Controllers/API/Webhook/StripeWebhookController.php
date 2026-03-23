<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Integrations\Stripe\StripeClient;
use App\Mail\PaymentFailedMail;
use App\Jobs\Zoho\SyncOrderToZohoJob;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Enums\OrderStatus;
use App\Services\BookingService;
use App\Services\OrderService;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

                        // Re-validate slot availability before confirming the booking
                        if (!$this->bookingService->areSlotsStillAvailable($booking)) {
                            // Slot conflict: cancel the booking and initiate refund
                            $this->bookingService->cancelBookingDueToSlotConflict($booking);
                            \Log::warning('[stripe][webhook] Booking cancelled due to slot conflict after payment', [
                                'booking_id' => $booking->id,
                                'payment_intent_id' => $paymentIntentId,
                            ]);
                        } else {
                            $this->bookingRepo->update($booking, [
                                'status' => 'confirmed',
                                'payment_status' => 'paid',
                            ]);
                            $booking->refresh();

                            // If this booking is part of a batch, mark all bookings in the batch as paid
                            if ($booking->batch_id) {
                                $this->bookingService->markBatchBookingsPaid($booking->batch_id);
                                // Send confirmation for all bookings in batch
                                $batchBookings = $this->bookingService->getBookingsByBatchId($booking->batch_id);
                                foreach ($batchBookings as $batchBooking) {
                                    if ($batchBooking->status === 'confirmed') {
                                        $this->bookingService->sendBookingConfirmation($batchBooking);
                                    }
                                }
                            } else {
                                $this->bookingService->sendBookingConfirmation($booking);
                            }
                        }
                    }

                    if ($order->getTypeValue() === 'ecommerce' && !$wasAlreadyPaid) {
                        $this->orderService->sendOrderConfirmation($order);

                        if ($order->user_id) {
                            app(\App\Services\ProductDiscountTierService::class)
                                ->checkAndUpgradeUser($order->user);
                        }
                    }

                    // Handle gift card order - create purchase + send emails
                    if ($order->getTypeValue() === 'gift_card' && !$wasAlreadyPaid) {
                        $this->handleGiftCardPaymentSuccess($order);
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

                    // Send payment failed notification
                    $this->sendPaymentFailedNotification($order, $object);
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

    private function handleGiftCardPaymentSuccess($order): void
    {
        try {
            $meta = $order->meta ?? [];
            $giftCardId = $meta['gift_card_id'] ?? null;

            if (!$giftCardId) {
                \Log::error('[stripe][webhook] Gift card order missing gift_card_id in meta', ['order_id' => $order->id]);
                return;
            }

            // Don't create duplicate purchase
            $existing = \App\Models\GiftCardPurchase::where('order_id', $order->id)->first();
            if ($existing) {
                return;
            }

            $giftCard = \App\Models\GiftCard::find($giftCardId);
            if (!$giftCard) {
                \Log::error('[stripe][webhook] Gift card not found', ['gift_card_id' => $giftCardId]);
                return;
            }

            // Create purchase record now that payment is confirmed
            $purchase = \App\Models\GiftCardPurchase::create([
                'gift_card_id' => $giftCard->id,
                'order_id' => $order->id,
                'code' => \App\Models\GiftCardPurchase::generateCode(),
                'buyer_name' => $meta['customer_name'] ?? 'Unknown',
                'buyer_email' => $meta['customer_email'] ?? '',
                'buyer_phone' => $meta['customer_phone'] ?? null,
                'recipient_name' => $meta['recipient_name'] ?? 'Unknown',
                'recipient_email' => $meta['recipient_email'] ?? null,
                'amount' => $giftCard->price,
                'balance' => $giftCard->price,
                'currency' => $giftCard->currency,
                'status' => 'active',
                'expires_at' => now()->addYear(),
            ]);

            $purchase->load('giftCard');

            // Email buyer
            if ($purchase->buyer_email) {
                Mail::to($purchase->buyer_email)
                    ->queue(new \App\Mail\GiftCardPurchasedMail($purchase, 'buyer'));
            }

            // Email recipient
            if ($purchase->recipient_email) {
                Mail::to($purchase->recipient_email)
                    ->queue(new \App\Mail\GiftCardPurchasedMail($purchase, 'recipient'));
            }

            // Create lead for non-registered buyers
            if (!$order->user_id && $purchase->buyer_phone) {
                $phone = $purchase->buyer_phone;
                if (!\App\Models\User::where('mobile', $phone)->orWhere('email', $purchase->buyer_email)->exists()) {
                    if (!\App\Models\Lead::where('phone', $phone)->exists()) {
                        \App\Models\Lead::create([
                            'name' => $purchase->buyer_name,
                            'phone' => $phone,
                            'email' => $purchase->buyer_email,
                            'source' => 'order',
                            'status' => 'new',
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::error('[stripe][webhook] Failed to handle gift card payment success', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPaymentFailedNotification($order, array $stripeObject): void
    {
        try {
            $customerEmail = $this->orderService->getCustomerEmail($order);

            if (!$customerEmail) {
                return;
            }

            $booking = null;
            if ($order->orderable && $order->getTypeValue() === 'booking') {
                $booking = $order->orderable;
            }

            $failureReason = data_get($stripeObject, 'last_payment_error.message')
                ?? data_get($stripeObject, 'cancellation_reason')
                ?? 'Payment could not be processed';

            Mail::to($customerEmail)->queue(new PaymentFailedMail($order, $booking, $failureReason));
        } catch (\Throwable $e) {
            \Log::error('[stripe][webhook] Failed to send payment failed notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
