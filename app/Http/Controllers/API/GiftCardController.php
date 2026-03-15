<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

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
}
