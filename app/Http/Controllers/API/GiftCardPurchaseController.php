<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Integrations\Stripe\StripeClient;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GiftCardPurchaseController extends Controller
{
    public function __construct(protected StripeClient $stripeClient) {}

    /**
     * Create a Stripe PaymentIntent for a gift card purchase.
     * No order or purchase is created yet — that happens on payment success.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'giftCardId' => 'required|integer|exists:gift_cards,id',
            'buyerName' => 'required|string|max:255',
            'buyerEmail' => 'required|email|max:255',
            'buyerPhone' => 'nullable|string|max:50',
            'recipientName' => 'required|string|max:255',
            'recipientEmail' => 'nullable|email|max:255',
        ]);

        $giftCard = GiftCard::where('id', $request->giftCardId)
            ->where('status', 'active')
            ->firstOrFail();

        $amountMinor = (int) round(((float) $giftCard->price) * 100);

        $payload = [
            'amount' => $amountMinor,
            'currency' => strtolower($giftCard->currency ?? 'AED'),
            'description' => "Gift Card: {$giftCard->name}",
            'payment_method_types[]' => 'card',
            'receipt_email' => $request->buyerEmail,
            'metadata[type]' => 'gift_card',
            'metadata[gift_card_id]' => (string) $giftCard->id,
            'metadata[gift_card_name]' => $giftCard->name,
            'metadata[buyer_name]' => $request->buyerName,
            'metadata[buyer_email]' => $request->buyerEmail,
            'metadata[buyer_phone]' => $request->buyerPhone ?? '',
            'metadata[recipient_name]' => $request->recipientName,
            'metadata[recipient_email]' => $request->recipientEmail ?? '',
            'metadata[user_id]' => (string) (auth()->id() ?? ''),
        ];

        $paymentIntent = $this->stripeClient->createPaymentIntent($payload, (string) Str::uuid());

        return ApiResponse::success([
            'clientSecret' => $paymentIntent['client_secret'],
            'paymentIntentId' => $paymentIntent['id'],
            'amount' => (float) $giftCard->price,
            'currency' => $giftCard->currency,
        ], 'Payment initiated', 201);
    }

    /**
     * Confirm gift card payment — creates order + purchase after successful Stripe payment.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'paymentIntentId' => 'required|string',
        ]);

        // Verify payment with Stripe
        $paymentIntent = $this->stripeClient->retrievePaymentIntent($request->paymentIntentId);

        if (($paymentIntent['status'] ?? '') !== 'succeeded') {
            return ApiResponse::error(null, 'Payment not completed', 400);
        }

        $metadata = $paymentIntent['metadata'] ?? [];

        // Check not already processed
        $existingOrder = \App\Models\Order::where('meta->stripe_payment_intent_id', $request->paymentIntentId)->first();
        if ($existingOrder) {
            $purchase = \App\Models\GiftCardPurchase::where('order_id', $existingOrder->id)->first();
            return ApiResponse::success([
                'order' => ['id' => $existingOrder->id, 'reference' => $existingOrder->reference],
                'purchase' => $purchase ? ['code' => $purchase->code] : null,
            ], 'Already processed');
        }

        $giftCard = GiftCard::find($metadata['gift_card_id'] ?? 0);
        if (!$giftCard) {
            return ApiResponse::error(null, 'Gift card not found', 404);
        }

        $userId = !empty($metadata['user_id']) ? (int) $metadata['user_id'] : null;

        // Create order
        $order = \App\Models\Order::create([
            'user_id' => $userId,
            'type' => 'gift_card',
            'status' => \App\Enums\OrderStatus::Paid->value,
            'amount' => $giftCard->price,
            'currency' => $giftCard->currency,
            'paid_at' => now(),
            'reference' => 'GCO-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6)),
            'meta' => [
                'stripe_payment_intent_id' => $request->paymentIntentId,
                'customer_name' => $metadata['buyer_name'] ?? '',
                'customer_email' => $metadata['buyer_email'] ?? '',
                'customer_phone' => $metadata['buyer_phone'] ?? null,
                'recipient_name' => $metadata['recipient_name'] ?? '',
                'recipient_email' => $metadata['recipient_email'] ?? null,
                'gift_card_id' => $giftCard->id,
                'gift_card_name' => $giftCard->name,
            ],
        ]);

        // Create payment record
        \App\Models\Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'flow' => 'token_charge',
            'amount' => $giftCard->price,
            'currency' => $giftCard->currency,
            'status' => 'paid',
            'external_id' => $request->paymentIntentId,
            'paid_at' => now(),
            'raw' => $paymentIntent,
        ]);

        // Create gift card purchase
        $purchase = \App\Models\GiftCardPurchase::create([
            'gift_card_id' => $giftCard->id,
            'order_id' => $order->id,
            'code' => \App\Models\GiftCardPurchase::generateCode(),
            'buyer_name' => $metadata['buyer_name'] ?? 'Unknown',
            'buyer_email' => $metadata['buyer_email'] ?? '',
            'buyer_phone' => $metadata['buyer_phone'] ?? null,
            'recipient_name' => $metadata['recipient_name'] ?? 'Unknown',
            'recipient_email' => $metadata['recipient_email'] ?? null,
            'amount' => $giftCard->price,
            'balance' => $giftCard->price,
            'currency' => $giftCard->currency,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        // Send emails
        $purchase->load('giftCard');
        if ($purchase->buyer_email) {
            \Illuminate\Support\Facades\Mail::to($purchase->buyer_email)
                ->queue(new \App\Mail\GiftCardPurchasedMail($purchase, 'buyer'));
        }
        if ($purchase->recipient_email) {
            \Illuminate\Support\Facades\Mail::to($purchase->recipient_email)
                ->queue(new \App\Mail\GiftCardPurchasedMail($purchase, 'recipient'));
        }

        // Create lead for non-registered buyers
        if (!$userId && ($metadata['buyer_phone'] ?? null)) {
            $phone = $metadata['buyer_phone'];
            $email = $metadata['buyer_email'] ?? '';
            if (!\App\Models\User::where('mobile', $phone)->orWhere('email', $email)->exists()) {
                if (!\App\Models\Lead::where('phone', $phone)->exists()) {
                    \App\Models\Lead::create([
                        'name' => $metadata['buyer_name'] ?? 'Unknown',
                        'phone' => $phone,
                        'email' => $email,
                        'source' => 'order',
                        'status' => 'new',
                    ]);
                }
            }
        }

        return ApiResponse::success([
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
            ],
            'purchase' => [
                'code' => $purchase->code,
                'expiresAt' => $purchase->expires_at->toIso8601String(),
            ],
        ], 'Gift card purchased successfully');
    }
}
