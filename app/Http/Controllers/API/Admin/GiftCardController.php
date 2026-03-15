<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\GiftCardPurchase;
use App\Models\GiftCardUsage;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    // --- Gift Card CRUD ---

    public function index(): JsonResponse
    {
        $giftCards = GiftCard::orderBy('sort_order')
            ->orderBy('price')
            ->withCount('purchases')
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
                'status' => $gc->status,
                'sortOrder' => $gc->sort_order,
                'purchasesCount' => $gc->purchases_count,
                'createdAt' => $gc->created_at,
            ];
        });

        return ApiResponse::success(['giftCards' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'descriptionAr' => 'nullable|string',
            'price' => 'required|numeric|min:1',
            'currency' => 'nullable|string|max:10',
            'image' => 'nullable|string',
            'status' => 'nullable|in:active,draft',
            'sortOrder' => 'nullable|integer',
        ]);

        $giftCard = GiftCard::create([
            'name' => $request->name,
            'name_ar' => $request->nameAr,
            'description' => $request->description,
            'descriptionAr' => $request->descriptionAr,
            'price' => $request->price,
            'currency' => $request->currency ?? 'AED',
            'image' => $request->image,
            'status' => $request->status ?? 'active',
            'sort_order' => $request->sortOrder ?? 0,
        ]);

        return ApiResponse::success([
            'giftCard' => $this->formatGiftCard($giftCard),
        ], 'Gift card created successfully', 201);
    }

    public function update(Request $request, GiftCard $giftCard): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'descriptionAr' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:1',
            'currency' => 'nullable|string|max:10',
            'image' => 'nullable|string',
            'status' => 'nullable|in:active,draft',
            'sortOrder' => 'nullable|integer',
        ]);

        $giftCard->update(array_filter([
            'name' => $request->name,
            'name_ar' => $request->nameAr,
            'description' => $request->description,
            'description_ar' => $request->descriptionAr,
            'price' => $request->price,
            'currency' => $request->currency,
            'image' => $request->image,
            'status' => $request->status,
            'sort_order' => $request->sortOrder,
        ], fn($v) => $v !== null));

        return ApiResponse::success([
            'giftCard' => $this->formatGiftCard($giftCard->fresh()),
        ], 'Gift card updated successfully');
    }

    public function destroy(GiftCard $giftCard): JsonResponse
    {
        $giftCard->delete();
        return ApiResponse::success([], 'Gift card deleted successfully');
    }

    // --- Purchase Management ---

    public function purchases(Request $request): JsonResponse
    {
        $query = GiftCardPurchase::with(['giftCard', 'usages.verifier'])
            ->orderBy('created_at', 'desc');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('buyer_name', 'LIKE', "%{$search}%")
                    ->orWhere('buyer_email', 'LIKE', "%{$search}%")
                    ->orWhere('recipient_name', 'LIKE', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $purchases = $query->paginate($perPage);

        $items = collect($purchases->items())->map(function (GiftCardPurchase $p) {
            return [
                'id' => $p->id,
                'code' => $p->code,
                'giftCard' => $p->giftCard ? [
                    'id' => $p->giftCard->id,
                    'name' => $p->giftCard->name,
                    'image' => $p->giftCard->image ? asset('storage/' . $p->giftCard->image) : null,
                ] : null,
                'buyerName' => $p->buyer_name,
                'buyerEmail' => $p->buyer_email,
                'buyerPhone' => $p->buyer_phone,
                'recipientName' => $p->recipient_name,
                'recipientEmail' => $p->recipient_email,
                'amount' => (float) $p->amount,
                'balance' => (float) $p->balance,
                'currency' => $p->currency,
                'status' => $p->status,
                'expiresAt' => $p->expires_at?->toIso8601String(),
                'isExpired' => $p->isExpired(),
                'createdAt' => $p->created_at?->toIso8601String(),
                'usages' => $p->usages->map(function (GiftCardUsage $u) {
                    return [
                        'id' => $u->id,
                        'amountUsed' => (float) $u->amount_used,
                        'usedForType' => $u->used_for_type,
                        'usedForId' => $u->used_for_id,
                        'usedForName' => $u->used_for_name ?? $u->used_for,
                        'notes' => $u->notes,
                        'verifiedBy' => $u->verifier ? [
                            'id' => $u->verifier->id,
                            'name' => $u->verifier->name,
                        ] : null,
                        'createdAt' => $u->created_at?->toIso8601String(),
                    ];
                }),
            ];
        });

        return ApiResponse::success([
            'purchases' => $items,
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    public function recordUsage(Request $request, GiftCardPurchase $giftCardPurchase): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'usedForType' => 'required|in:service,product',
            'usedForId' => 'required|integer',
            'usedForName' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($giftCardPurchase->isExpired()) {
            return ApiResponse::error(null, 'This gift card has expired', 422);
        }

        if ($giftCardPurchase->balance <= 0) {
            return ApiResponse::error(null, 'This gift card has no remaining balance', 422);
        }

        $amount = min($request->amount, (float) $giftCardPurchase->balance);

        GiftCardUsage::create([
            'gift_card_purchase_id' => $giftCardPurchase->id,
            'amount_used' => $amount,
            'used_for_type' => $request->usedForType,
            'used_for_id' => $request->usedForId,
            'used_for_name' => $request->usedForName,
            'used_for' => $request->usedForName, // keep backward compat
            'notes' => $request->notes,
            'verified_by' => auth()->id(),
        ]);

        $newBalance = (float) $giftCardPurchase->balance - $amount;
        $giftCardPurchase->update([
            'balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'used' : 'active',
        ]);

        return ApiResponse::success([
            'balance' => $newBalance,
            'status' => $giftCardPurchase->status,
        ], 'Usage recorded successfully');
    }

    public function searchItems(Request $request): JsonResponse
    {
        $type = $request->get('type'); // 'service' or 'product'
        $search = $request->get('search', '');
        $page = (int) $request->get('page', 1);
        $perPage = 10;

        if ($type === 'service') {
            $query = \App\Models\SubService::with('service')
                ->where(function ($q) use ($search) {
                    if ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('name_ar', 'LIKE', "%{$search}%");
                    }
                })
                ->orderBy('name');

            $results = $query->paginate($perPage, ['*'], 'page', $page);

            $items = collect($results->items())->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'nameAr' => $s->name_ar,
                    'price' => (float) $s->price,
                    'currency' => $s->currency ?? 'AED',
                    'parentName' => $s->service?->name,
                ];
            });
        } elseif ($type === 'product') {
            $query = \App\Models\Product::where(function ($q) use ($search) {
                    if ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('name_ar', 'LIKE', "%{$search}%");
                    }
                })
                ->where('status', 'active')
                ->orderBy('name');

            $results = $query->paginate($perPage, ['*'], 'page', $page);

            $items = collect($results->items())->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'nameAr' => $p->name_ar,
                    'price' => (float) $p->getFinalPrice(),
                    'currency' => $p->currency ?? 'AED',
                ];
            });
        } else {
            return ApiResponse::error(null, 'Invalid type', 422);
        }

        return ApiResponse::success([
            'items' => $items,
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    private function formatGiftCard(GiftCard $gc): array
    {
        return [
            'id' => $gc->id,
            'name' => $gc->name,
            'nameAr' => $gc->name_ar,
            'description' => $gc->description,
            'descriptionAr' => $gc->description_ar,
            'price' => (float) $gc->price,
            'currency' => $gc->currency,
            'image' => $gc->image ? asset('storage/' . $gc->image) : null,
            'status' => $gc->status,
            'sortOrder' => $gc->sort_order,
            'createdAt' => $gc->created_at,
        ];
    }
}
