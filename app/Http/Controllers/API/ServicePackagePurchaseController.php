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
    public function __construct(protected StripeClient $stripeClient) {}

    /**
     * POST /service-packages/purchase — create Stripe PaymentIntent.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'servicePackageId' => 'required|integer|exists:service_packages,id',
        ]);

        $package = ServicePackage::where('id', $request->servicePackageId)
            ->where('status', 'active')
            ->firstOrFail();

        $user = auth()->user();
        $vatRate = 0.05;
        $basePrice = (float) $package->price;
        $taxAmount = round($basePrice * $vatRate, 2);
        $totalAmount = round($basePrice + $taxAmount, 2);
        $amountMinor = (int) round($totalAmount * 100);

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

        $paymentIntent = $this->stripeClient->createPaymentIntent($payload, (string) Str::uuid());

        return ApiResponse::success([
            'clientSecret' => $paymentIntent['client_secret'],
            'paymentIntentId' => $paymentIntent['id'],
            'amount' => $totalAmount,
            'basePrice' => $basePrice,
            'taxAmount' => $taxAmount,
            'currency' => $package->currency,
        ], 'Payment initiated', 201);
    }

    /**
     * POST /service-packages/confirm — verify payment and create purchase record.
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

        // Verify the captured amount covers the package price (base + VAT), so a
        // stale/tampered intent can't grant a package for less than it costs.
        $expectedTotal = round((float) $package->price + round((float) $package->price * 0.05, 2), 2);
        $capturedMinor = (int) (($paymentIntent['amount_received'] ?? $paymentIntent['amount'] ?? 0));
        if ($capturedMinor < (int) round($expectedTotal * 100)) {
            return ApiResponse::error(null, 'Payment amount mismatch', 400);
        }

        $userId = !empty($metadata['user_id']) ? (int) $metadata['user_id'] : auth()->id();

        $purchase = DB::transaction(function () use ($package, $metadata, $paymentIntent, $request, $userId) {
            $vatRate = 0.05;
            $basePrice = (float) $package->price;
            $taxAmount = round($basePrice * $vatRate, 2);
            $totalAmount = round($basePrice + $taxAmount, 2);

            $order = \App\Models\Order::create([
                'user_id' => $userId,
                'type' => 'service_package',
                'status' => \App\Enums\OrderStatus::Paid->value,
                'amount' => $totalAmount,
                'currency' => $package->currency,
                'paid_at' => now(),
                'reference' => 'SPO-' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 6)),
                'meta' => [
                    'stripe_payment_intent_id' => $request->paymentIntentId,
                    'service_package_id' => $package->id,
                    'service_package_name' => $package->name,
                    'base_price' => $basePrice,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ],
            ]);

            \App\Models\Payment::create([
                'order_id' => $order->id,
                'provider' => 'stripe',
                'flow' => 'token_charge',
                'amount' => $totalAmount,
                'currency' => $package->currency,
                'status' => 'paid',
                'external_id' => $request->paymentIntentId,
                'paid_at' => now(),
                'raw' => $paymentIntent,
            ]);

            $purchase = ServicePackagePurchase::create([
                'service_package_id' => $package->id,
                'user_id' => $userId,
                'order_id' => $order->id,
                'code' => ServicePackagePurchase::generateCode(),
                'status' => 'active',
                'purchased_at' => now(),
                'expires_at' => now()->addDays($package->validity_days),
            ]);

            return $purchase;
        });

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
}
