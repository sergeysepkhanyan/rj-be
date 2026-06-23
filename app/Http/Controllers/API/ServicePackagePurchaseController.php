<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Integrations\Stripe\StripeClient;
use App\Models\ServicePackage;
use App\Models\ServicePackagePurchase;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicePackagePurchaseController extends Controller
{
    private const VAT_RATE = 0.05;

    public function __construct(protected StripeClient $stripeClient) {}

    /**
     * POST /service-packages/purchase — start a purchase.
     * If a gift card fully covers the price the order is created immediately
     * (no Stripe). Otherwise a PaymentIntent for the remaining amount is returned.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'servicePackageId' => 'required|integer|exists:service_packages,id',
            'giftCardCode' => 'nullable|string',
        ]);

        $package = ServicePackage::where('id', $request->servicePackageId)
            ->where('status', 'active')
            ->firstOrFail();

        $user = auth()->user();
        $basePrice = (float) $package->price;
        $taxAmount = round($basePrice * self::VAT_RATE, 2);
        $totalAmount = round($basePrice + $taxAmount, 2);

        $giftCardCode = $request->input('giftCardCode') ?? $request->input('gift_card_code');
        $giftCardApplied = 0.0;

        if ($giftCardCode) {
            $purchaseGc = \App\Models\GiftCardPurchase::where('code', $giftCardCode)
                ->where('status', 'active')
                ->first();

            if (!$purchaseGc || $purchaseGc->isExpired() || (float) $purchaseGc->balance <= 0) {
                return ApiResponse::error(
                    ['giftCardCode' => ['Gift card is not valid or has no balance']],
                    'Invalid gift card',
                    422
                );
            }

            $giftCardApplied = min((float) $purchaseGc->balance, $totalAmount);
            $remaining = round($totalAmount - $giftCardApplied, 2);

            // Gift card covers the whole price — finalize now, no card charge.
            if ($remaining <= 0.005) {
                $purchase = DB::transaction(fn () => $this->finalizePackagePurchase(
                    $package,
                    (int) $user->id,
                    $basePrice,
                    $taxAmount,
                    $totalAmount,
                    $giftCardCode,
                    null,
                    null
                ));

                $order = $purchase->order;

                return ApiResponse::success([
                    'fullyCovered' => true,
                    'order' => ['id' => $order->id, 'reference' => $order->reference],
                    'purchase' => [
                        'code' => $purchase->code,
                        'expiresAt' => $purchase->expires_at->toIso8601String(),
                    ],
                ], 'Service package purchased successfully', 201);
            }
        }

        // Charge the remaining amount (full price when no gift card) via Stripe.
        $chargeAmount = round($totalAmount - $giftCardApplied, 2);
        $amountMinor = (int) round($chargeAmount * 100);

        $payload = [
            'amount' => $amountMinor,
            'currency' => strtolower($package->currency ?? 'AED'),
            'description' => "Service Package: {$package->name}",
            'payment_method_types[]' => 'card',
            'receipt_email' => $user->email,
            'metadata[type]' => 'service_package',
            'metadata[service_package_id]' => (string) $package->id,
            'metadata[service_package_name]' => $package->name,
            'metadata[user_id]' => (string) $user->id,
            'metadata[base_price]' => (string) $basePrice,
            'metadata[tax_amount]' => (string) $taxAmount,
        ];
        if ($giftCardCode && $giftCardApplied > 0) {
            $payload['metadata[gift_card_code]'] = $giftCardCode;
        }

        $paymentIntent = $this->stripeClient->createPaymentIntent($payload, (string) Str::uuid());

        return ApiResponse::success([
            'clientSecret' => $paymentIntent['client_secret'],
            'paymentIntentId' => $paymentIntent['id'],
            'amount' => $totalAmount,
            'basePrice' => $basePrice,
            'taxAmount' => $taxAmount,
            'giftCardAmount' => $giftCardApplied,
            'amountDue' => $chargeAmount,
            'currency' => $package->currency,
        ], 'Payment initiated', 201);
    }

    /**
     * POST /service-packages/confirm — verify the Stripe charge and create the purchase.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'paymentIntentId' => 'required|string',
        ]);

        $paymentIntent = $this->stripeClient->retrievePaymentIntent($request->paymentIntentId);

        if (($paymentIntent['status'] ?? '') !== 'succeeded') {
            return ApiResponse::error(null, 'Payment not completed', 400);
        }

        $metadata = $paymentIntent['metadata'] ?? [];

        // Idempotency — don't create a second order for the same intent.
        $existingOrder = \App\Models\Order::where('meta->stripe_payment_intent_id', $request->paymentIntentId)->first();
        if ($existingOrder) {
            $purchase = ServicePackagePurchase::where('order_id', $existingOrder->id)->first();
            return ApiResponse::success([
                'order' => ['id' => $existingOrder->id, 'reference' => $existingOrder->reference],
                'purchase' => $purchase ? ['code' => $purchase->code] : null,
            ], 'Already processed');
        }

        $package = ServicePackage::find($metadata['service_package_id'] ?? 0);
        if (!$package) {
            return ApiResponse::error(null, 'Service package not found', 404);
        }

        $basePrice = (float) $package->price;
        $taxAmount = round($basePrice * self::VAT_RATE, 2);
        $totalAmount = round($basePrice + $taxAmount, 2);
        $userId = !empty($metadata['user_id']) ? (int) $metadata['user_id'] : auth()->id();
        $giftCardCode = $metadata['gift_card_code'] ?? null;

        try {
            $purchase = DB::transaction(fn () => $this->finalizePackagePurchase(
                $package,
                $userId,
                $basePrice,
                $taxAmount,
                $totalAmount,
                $giftCardCode,
                $request->paymentIntentId,
                $paymentIntent
            ));
        } catch (\RuntimeException $e) {
            return ApiResponse::error(null, $e->getMessage(), 422);
        }

        $order = $purchase->order;

        return ApiResponse::success([
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
            ],
            'purchase' => [
                'code' => $purchase->code,
                'expiresAt' => $purchase->expires_at->toIso8601String(),
            ],
        ], 'Service package purchased successfully');
    }

    /**
     * Create the paid order + payment + purchase, redeeming the gift card if any.
     * Must run inside a DB transaction. `order.amount` holds the cash actually
     * charged (gift-card revenue is recognised when the card is bought, so the
     * gift-card portion is not re-counted here); `meta.total_amount` holds the
     * gross so invoices/admin can still show base + VAT.
     *
     * @param  array<string,mixed>|null  $stripeRaw
     */
    private function finalizePackagePurchase(
        ServicePackage $package,
        int $userId,
        float $basePrice,
        float $taxAmount,
        float $totalAmount,
        ?string $giftCardCode,
        ?string $stripePaymentIntentId,
        ?array $stripeRaw
    ): ServicePackagePurchase {
        $giftCardAmount = 0.0;
        $purchaseGc = null;

        if ($giftCardCode) {
            $purchaseGc = \App\Models\GiftCardPurchase::where('code', $giftCardCode)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$purchaseGc || $purchaseGc->isExpired() || (float) $purchaseGc->balance <= 0) {
                throw new \RuntimeException('Gift card is not valid or has no balance.');
            }

            $giftCardAmount = min((float) $purchaseGc->balance, $totalAmount);
        }

        $cashCharged = round($totalAmount - $giftCardAmount, 2);
        if ($cashCharged < 0) {
            $cashCharged = 0.0;
        }

        // Verify Stripe captured at least the cash portion that was due.
        if ($cashCharged > 0) {
            $capturedMinor = (int) (data_get($stripeRaw, 'amount_received') ?? data_get($stripeRaw, 'amount') ?? 0);
            if ($capturedMinor < (int) round($cashCharged * 100)) {
                throw new \RuntimeException('Payment amount mismatch');
            }
        }

        if ($purchaseGc) {
            $newBalance = max(0, (float) $purchaseGc->balance - $giftCardAmount);
            $purchaseGc->update([
                'balance' => $newBalance,
                ...($newBalance <= 0 ? ['status' => 'used'] : []),
            ]);
        }

        $order = \App\Models\Order::create([
            'user_id' => $userId,
            'type' => 'service_package',
            'status' => \App\Enums\OrderStatus::Paid->value,
            'amount' => $cashCharged,
            'currency' => $package->currency,
            'paid_at' => now(),
            'reference' => 'SPO-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6)),
            'meta' => [
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'service_package_id' => $package->id,
                'service_package_name' => $package->name,
                'base_price' => $basePrice,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'gift_card_code' => $giftCardCode,
                'gift_card_amount' => $giftCardAmount > 0 ? $giftCardAmount : null,
                'payment_method' => $cashCharged > 0 ? 'card' : 'gift_card',
            ],
        ]);

        if ($cashCharged > 0 && $stripePaymentIntentId) {
            \App\Models\Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'flow' => 'token_charge',
                'amount' => $cashCharged,
                'currency' => $package->currency,
                'status' => 'paid',
                'external_id' => $stripePaymentIntentId,
                'paid_at' => now(),
                'raw' => $stripeRaw,
            ]);
        } elseif ($giftCardAmount > 0) {
            \App\Models\Payment::create([
                'order_id' => $order->id,
                'provider' => 'gift_card',
                'flow' => 'manual',
                'amount' => $cashCharged,
                'currency' => $package->currency,
                'status' => 'paid',
                'paid_at' => now(),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        }

        if ($purchaseGc && $giftCardAmount > 0) {
            \App\Models\GiftCardUsage::create([
                'gift_card_purchase_id' => $purchaseGc->id,
                'amount_used' => $giftCardAmount,
                'used_for_type' => 'service_package',
                'used_for_id' => $order->id,
                'used_for_name' => $package->name,
                'used_for' => 'order',
                'notes' => 'Applied to service package purchase',
                'verified_by' => $userId,
            ]);

            if ($purchaseGc->buyer_email) {
                \Illuminate\Support\Facades\Mail::to($purchaseGc->buyer_email)
                    ->queue(new \App\Mail\GiftCardBalanceDeductedMail($purchaseGc, $giftCardAmount));
            }
        }

        return ServicePackagePurchase::create([
            'service_package_id' => $package->id,
            'user_id' => $userId,
            'order_id' => $order->id,
            'code' => ServicePackagePurchase::generateCode(),
            'status' => 'active',
            'purchased_at' => now(),
            'expires_at' => now()->addDays($package->validity_days),
        ]);
    }
}
