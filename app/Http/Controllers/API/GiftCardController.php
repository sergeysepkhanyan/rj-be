<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\GiftCardPurchase;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function index(): JsonResponse
    {
        $giftCards = GiftCard::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        $items = $giftCards->map(function (GiftCard $gc) {
            return [
                'id' => $gc->id,
                'name' => $gc->name,
                'nameAr' => $gc->name_ar,
                'description' => $gc->description,
                'descriptionAr' => $gc->description_ar,
                'price' => (float) $gc->price,
                'currency' => $gc->currency,
                'image' => $gc->image ? asset('storage/' . $gc->image) : null,
            ];
        });

        return ApiResponse::success(['giftCards' => $items]);
    }

    public function validate(Request $request): JsonResponse
    {
        $code = $request->input('code', '');

        if (empty($code)) {
            return ApiResponse::error(
                ['code' => 'Gift card code is required.'],
                __('validation.failed'),
                422
            );
        }

        $purchase = GiftCardPurchase::where('code', $code)
            ->where('status', 'active')
            ->first();

        if (!$purchase) {
            return ApiResponse::error(
                ['code' => 'Gift card not found or is not active.'],
                __('validation.failed'),
                422
            );
        }

        if ($purchase->isExpired()) {
            return ApiResponse::error(
                ['code' => 'This gift card has expired.'],
                __('validation.failed'),
                422
            );
        }

        if ($purchase->balance <= 0) {
            return ApiResponse::error(
                ['code' => 'This gift card has no remaining balance.'],
                __('validation.failed'),
                422
            );
        }

        return ApiResponse::success([
            'giftCard' => [
                'code' => $purchase->code,
                'balance' => (float) $purchase->balance,
                'currency' => $purchase->currency ?? 'AED',
                'expiresAt' => $purchase->expires_at?->toISOString(),
            ],
        ], 'Gift card is valid.');
    }
}
