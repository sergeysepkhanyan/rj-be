<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddReferralRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ClientDetailResource;
use App\Models\User;
use App\Models\Lead;
use App\Models\ClientNote;
use App\Models\Booking;
use App\Models\BookingReferral;
use App\Models\Order;
use App\Mail\DiscountTierChangedMail;
use App\Models\Referral;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ClientsController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'min:2', 'max:255', 'regex:/^(?=.*\pL)[\pL\pM\s\'\-.]+$/u'],
            'lastName'  => ['nullable', 'string', 'max:255'],
            'email'     => ['required', 'email:rfc', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->whereNull('deleted_at')->where('has_account', true)],
            'mobile'    => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,18}$/'],
            'source'    => ['nullable', 'string', 'in:online,walk_in,offline,booking,manual'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ], [
            'firstName.regex' => 'First name must contain letters.',
            'mobile.regex' => 'Mobile must include a country code and at least 7 digits.',
        ]);

        $user = app(\App\Services\CustomerService::class)->resolveForTransaction([
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'] ?? null,
            'name' => trim($validated['firstName'] . ' ' . ($validated['lastName'] ?? '')) ?: null,
            'email' => $validated['email'],
            'phone' => $validated['mobile'],
            'source' => $validated['source'] ?? 'manual',
        ]);

        $user->forceFill([
            'mobile' => $validated['mobile'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (!empty($validated['notes'])) {
            ClientNote::create([
                'client_id' => $user->id,
                'created_by' => auth()->id(),
                'content' => $validated['notes'],
            ]);
        }

        return ApiResponse::success([
            'user' => new ClientResource($user->fresh()->load('referral')),
        ], 'Client created successfully');
    }

    /**
     * Edit an existing client's contact details from the CRM tab.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['sometimes', 'string', 'min:2', 'max:255', 'regex:/^(?=.*\pL)[\pL\pM\s\'\-.]+$/u'],
            'lastName'  => ['nullable', 'string', 'max:255'],
            'email'     => ['sometimes', 'email:rfc', 'max:255', 'unique:users,email,' . $user->id . ',id,deleted_at,NULL'],
            'mobile'    => ['sometimes', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,18}$/'],
            'source'    => ['nullable', 'string', 'in:online,walk_in,offline,booking,manual'],
        ], [
            'firstName.regex' => 'First name must contain letters.',
            'mobile.regex' => 'Mobile must include a country code and at least 7 digits.',
        ]);

        $update = [];
        if (array_key_exists('firstName', $validated)) $update['first_name'] = $validated['firstName'];
        if (array_key_exists('lastName', $validated))  $update['last_name']  = $validated['lastName'];
        if (array_key_exists('email', $validated))     $update['email']      = $validated['email'] !== null ? trim(strtolower($validated['email'])) : null;
        if (array_key_exists('mobile', $validated))    $update['mobile']     = $validated['mobile'];
        if (array_key_exists('source', $validated))    $update['registration_source'] = $validated['source'];

        // Keep the `name` column in sync so ClientDetailResource (which
        // prefers `name` over first_name/last_name) always shows the
        // latest value.
        if (array_key_exists('first_name', $update) || array_key_exists('last_name', $update)) {
            $firstName = $update['first_name'] ?? $user->first_name ?? '';
            $lastName  = $update['last_name']  ?? $user->last_name  ?? '';
            $update['name'] = trim($firstName . ' ' . $lastName) ?: null;
        }

        $user->update($update);

        return ApiResponse::success([
            'user' => new ClientResource($user->fresh()->load('referral')),
        ], 'Client updated successfully');
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $page    = (int) $request->get('page', 1);

        $filters = [
            'status'  => $request->get('status'),
            'account' => $request->get('account'),
            'search'  => $request->get('search'),
        ];

        $clients = $this->userService->getPaginatedClients($perPage, $page, $filters);

        return ApiResponse::success([
            'users' => ClientResource::collection($clients),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
            'links' => [
                'first' => $clients->url(1),
                'last' => $clients->url($clients->lastPage()),
                'prev' => $clients->previousPageUrl(),
                'next' => $clients->nextPageUrl(),
            ],
        ], __('success.client.list'));
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['referral', 'manualReferral', 'notes.createdBy']);

        // Get confirmed bookings count and total (paid, gift, or confirmed pay_later)
        $confirmedBookings = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->whereIn('status', ['confirmed', 'completed'])
            ->get();

        $bookingsCount = $confirmedBookings->count();
        $bookingsTotal = $confirmedBookings->sum('final_price');

        // Get cancelled bookings count (cancelled by the user themselves)
        $cancelledByUserCount = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->where('status', 'cancelled')
            ->where('cancelled_by_user_id', $user->id)
            ->count();

        // Get total cancelled bookings (including cancellations by admin)
        $totalCancelledCount = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->where('status', 'cancelled')
            ->count();

        // Get no-show count (bookings that were marked as no-show or expired due to non-payment)
        $noShowCount = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->where(function ($query) {
                $query->where('cancel_reason', 'no_show')
                    ->orWhere('cancel_reason', 'payment_timeout');
            })
            ->count();

        // Get confirmed product orders count and total (paid, fulfilled, processing, shipped)
        $confirmedOrders = Order::where('user_id', $user->id)
            ->where('type', 'ecommerce')
            ->whereIn('status', ['paid', 'fulfilled', 'processing', 'shipped', 'return_requested', 'return_approved', 'return_rejected', 'refunded'])
            ->get();

        $ordersCount = $confirmedOrders->count();
        $ordersTotal = $confirmedOrders->sum('amount');

        $wishlistCount = \App\Models\Wishlist::where('user_id', $user->id)->count();

        return ApiResponse::success([
            'client' => new ClientDetailResource($user),
            'stats' => [
                'bookings_count' => $bookingsCount,
                'bookings_total' => (float) $bookingsTotal,
                'cancelled_by_user_count' => $cancelledByUserCount,
                'total_cancelled_count' => $totalCancelledCount,
                'no_show_count' => $noShowCount,
                'orders_count' => $ordersCount,
                'orders_total' => (float) $ordersTotal,
                'total_spent' => (float) ($bookingsTotal + $ordersTotal),
                'wishlist_count' => $wishlistCount,
                'referral_count' => BookingReferral::where('referrer_user_id', $user->id)->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function bookings(User $user, Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);

        $bookings = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->with(['master', 'services.bookable', 'services.master', 'servicePackagePurchase.servicePackage'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($perPage);

        return ApiResponse::success([
            'bookings' => $bookings->items(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function orders(User $user, Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);

        $orders = Order::where('user_id', $user->id)
            ->where('type', 'ecommerce')
            ->with(['items.product', 'shippingAddress.country', 'orderReturn'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $formattedOrders = collect($orders->items())->map(function ($order) {
            return [
                'id' => $order->id,
                'reference' => $order->reference,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'type' => $order->type, // 'product' or 'booking'
                'status' => $order->status,
                'payment_status' => in_array($order->status, ['return_requested', 'return_approved', 'return_rejected', 'refunded', 'gift'])
                    ? $order->status
                    : ($order->paid_at ? 'paid' : ($order->status === 'cancelled' ? 'cancelled' : 'unpaid')),
                'delivery_status' => $order->delivery_status,
                'created_at' => $order->created_at,
                'paid_at' => $order->paid_at,
                'items' => $order->type === 'gift_card'
                    ? [[
                        'id' => $order->meta['gift_card_id'] ?? 0,
                        'quantity' => 1,
                        'price' => $order->amount,
                        'product' => [
                            'id' => $order->meta['gift_card_id'] ?? 0,
                            'name' => $order->meta['gift_card_name'] ?? 'Gift Card',
                            'image' => null,
                        ],
                    ]]
                    : $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'price' => $item->unit_price,
                            'product' => $item->product ? [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'image' => $item->product->main_image
                                    ? asset('storage/' . $item->product->main_image)
                                    : null,
                            ] : null,
                        ];
                    }),
                'shippingAddress' => $order->shippingAddress ? [
                    'city' => $order->shippingAddress->city,
                    'state' => $order->shippingAddress->country?->name ?? null,
                ] : null,
            ];
        });

        return ApiResponse::success([
            'orders' => $formattedOrders,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function wishlist(User $user): JsonResponse
    {
        $wishlistProducts = $user->wishlistProducts()
            ->with(['files', 'details', 'productCategory'])
            ->orderByPivot('created_at', 'desc')
            ->get();

        return ApiResponse::success([
            'items' => \App\Http\Resources\ProductResource::collection($wishlistProducts),
            'count' => $wishlistProducts->count(),
        ]);
    }

    public function toggleLock(User $user): JsonResponse
    {
        $newStatus = $user->status === 'locked' ? 'active' : 'locked';
        $user->update(['status' => $newStatus]);

        return ApiResponse::success([
            'client' => new ClientDetailResource($user),
            'status' => $newStatus,
        ], $newStatus === 'locked' ? 'Client locked successfully' : 'Client unlocked successfully');
    }

    public function addNote(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $note = ClientNote::create([
            'client_id' => $user->id,
            'created_by' => auth()->id(),
            'content' => $request->content,
        ]);

        $note->load('createdBy');

        return ApiResponse::success([
            'note' => [
                'id' => $note->id,
                'content' => $note->content,
                'createdAt' => $note->created_at,
                'createdBy' => [
                    'id' => $note->createdBy->id,
                    'name' => $note->createdBy->name,
                ],
            ],
        ], 'Note added successfully');
    }

    public function deleteNote(User $user, ClientNote $note): JsonResponse
    {
        if ($note->client_id !== $user->id) {
            return ApiResponse::error('Note does not belong to this client', 403);
        }

        $note->delete();

        return ApiResponse::success([], 'Note deleted successfully');
    }

    public function addReferral(AddReferralRequest $request, User $user): JsonResponse
    {
        $client = $this->userService->updateUser($user, $request->all());
        $client->load(['referral', 'manualReferral']);

        // Send email notification when admin assigns/changes a manual discount tier
        $manualReferral = $client->manualReferral;
        if ($manualReferral && $manualReferral->enabled && $client->email) {
            Mail::to($client->email)->queue(new DiscountTierChangedMail($client, $manualReferral));
        }

        return ApiResponse::success([
            'user' => new ClientResource($client),
        ], __('success.client.referral_added'));
    }

    public function referrals(User $user, Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);

        $referrals = BookingReferral::where('referrer_user_id', $user->id)
            ->with(['booking.client', 'booking.services.bookable'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::success([
            'referrals' => collect($referrals->items())->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'bookingId' => $referral->booking_id,
                    'status' => $referral->status,
                    'booking' => $referral->booking ? [
                        'id' => $referral->booking->id,
                        'reference' => $referral->booking->reference ?? "BK-{$referral->booking->id}",
                        'date' => $referral->booking->date?->format('Y-m-d'),
                        'status' => $referral->booking->status,
                        'customerName' => $referral->booking->customer_name
                            ?? ($referral->booking->client ? trim(($referral->booking->client->name ?? '') . ' ' . ($referral->booking->client->last_name ?? '')) : null)
                            ?? 'Unknown',
                        'customerEmail' => $referral->booking->customer_email
                            ?? $referral->booking->client?->email,
                    ] : null,
                    'createdAt' => $referral->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
            ],
        ]);
    }

    /**
     * Search for clients and leads by name or phone number.
     * Used for autocomplete in admin booking creation.
     */
    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('q', ''));

        if (strlen($search) < 2) {
            return ApiResponse::success(['results' => []], 'Search query too short');
        }

        $limit = min((int) $request->get('limit', 10), 20);

        // Search in users (CRM clients) - also search in first_name and last_name
        $users = User::where(function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%")
                ->orWhere('mobile', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%");
        })
            ->whereHas('role', fn($q) => $q->where('slug', 'client'))
            ->with(['referral', 'manualReferral'])
            ->limit($limit)
            ->get();

        // Search in leads (non-converted only)
        $leads = Lead::where(function ($query) use ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('phone', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%");
        })
            ->whereNull('converted_user_id')
            ->with('referral')
            ->limit($limit)
            ->get();

        // Format results
        $results = [];

        foreach ($users as $user) {
            // Build full name from first_name + last_name if name is empty (same as ClientResource)
            $fullName = $user->name;
            if (empty($fullName)) {
                $firstName = $user->first_name ?? '';
                $lastName = $user->last_name ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: null;
            }

            // Determine the active referral (manual takes priority)
            // Show discount without visit threshold check (same as CRM page)
            $referral = $user->manualReferral ?? $user->referral;
            $discount = null;

            if ($referral && $referral->enabled) {
                $discount = [
                    'id' => $referral->id,
                    'name' => $referral->name,
                    'type' => $referral->type === 'percentage' ? 'percent' : 'fixed',
                    'value' => (float) $referral->value,
                ];
            }

            // Get active package purchases for this user
            $activePackages = \App\Models\ServicePackagePurchase::where('user_id', $user->id)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->with(['servicePackage.items.subService'])
                ->get()
                ->map(function ($purchase) {
                    return [
                        'purchaseId' => $purchase->id,
                        'packageName' => $purchase->servicePackage?->name,
                        'expiresAt' => $purchase->expires_at?->toIso8601String(),
                        'items' => $purchase->servicePackage?->items->map(function ($item) use ($purchase) {
                            $usedCount = $purchase->usages()->where('service_package_item_id', $item->id)->count();
                            $remaining = $item->isUnlimited() ? -1 : max(0, $item->total_visits - $usedCount);
                            // Skip items with no remaining visits
                            if (!$item->isUnlimited() && $remaining <= 0) return null;
                            return [
                                'itemId' => $item->id,
                                'subServiceId' => $item->sub_service_id,
                                'subServiceName' => $item->subService?->name,
                                'totalVisits' => $item->total_visits,
                                'isUnlimited' => $item->isUnlimited(),
                                'remainingVisits' => $remaining,
                            ];
                        })->filter()->values(),
                    ];
                })
                ->filter(fn ($p) => $p['items']->isNotEmpty())
                ->values();

            $results[] = [
                'id' => $user->id,
                'type' => 'user',
                'name' => $fullName,
                'phone' => $user->mobile,
                'email' => $user->email,
                'discount' => $discount,
                'activePackages' => $activePackages,
            ];
        }

        foreach ($leads as $lead) {
            $discount = null;

            if ($lead->referral && $lead->referral->enabled) {
                $discount = [
                    'id' => $lead->referral->id,
                    'name' => $lead->referral->name,
                    'type' => $lead->referral->type === 'percentage' ? 'percent' : 'fixed',
                    'value' => (float) $lead->referral->value,
                ];
            }

            $results[] = [
                'id' => $lead->id,
                'type' => 'lead',
                'name' => $lead->name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'discount' => $discount,
            ];
        }

        // Sort by name
        usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // Limit total results
        $results = array_slice($results, 0, $limit);

        return ApiResponse::success(['results' => $results]);
    }

    /**
     * List a client's complimentary rewards (available + recently redeemed) so staff
     * can see and release them in-store against the real record.
     */
    public function rewards(User $user): \Illuminate\Http\JsonResponse
    {
        $rewards = \App\Models\ComplimentaryReward::where('user_id', $user->id)
            ->with('subService')
            ->orderByRaw("FIELD(status,'available','redeemed','expired')")
            ->orderByDesc('earned_at')
            ->get()
            ->map(fn ($reward) => [
                'id' => $reward->id,
                'status' => $reward->status,
                'subService' => $reward->subService ? [
                    'id' => $reward->subService->id,
                    'name' => $reward->subService->name,
                ] : null,
                'earnedAt' => $reward->earned_at,
                'redeemedAt' => $reward->redeemed_at,
            ]);

        return ApiResponse::success(['rewards' => $rewards]);
    }

    /**
     * Staff redeems a complimentary reward in-store (walk-in, no login needed).
     * Verified against the real client record; idempotent (only 'available' redeems).
     */
    public function redeemReward(User $user, \App\Models\ComplimentaryReward $reward): \Illuminate\Http\JsonResponse
    {
        if ((int) $reward->user_id !== (int) $user->id) {
            return ApiResponse::error(null, 'This reward does not belong to this client', 422);
        }

        if ($reward->status !== 'available') {
            return ApiResponse::error(null, 'This reward is not available for redemption', 422);
        }

        $reward->update([
            'status' => 'redeemed',
            'redeemed_at' => now(),
        ]);

        return ApiResponse::success([
            'reward' => [
                'id' => $reward->id,
                'status' => $reward->status,
                'redeemedAt' => $reward->redeemed_at,
            ],
        ], 'Reward redeemed');
    }

    /**
     * Phone-matched possible duplicates (different email, same phone) for staff to review.
     */
    public function possibleDuplicates(User $user): \Illuminate\Http\JsonResponse
    {
        $dupes = app(\App\Services\CustomerService::class)->possibleDuplicates($user);

        return ApiResponse::success([
            'duplicates' => $dupes->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name ?: trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                'email' => $u->email,
                'mobile' => $u->mobile,
                'customerStatus' => $u->customer_status,
                'hasAccount' => (bool) $u->has_account,
            ])->values(),
        ]);
    }

    /**
     * Staff-confirmed merge of a duplicate INTO this client (never automatic).
     */
    public function mergeDuplicate(User $user, User $duplicate): \Illuminate\Http\JsonResponse
    {
        if ((int) $user->id === (int) $duplicate->id) {
            return ApiResponse::error(null, 'Cannot merge a client into itself', 422);
        }

        app(\App\Services\CustomerService::class)->mergeCustomers($user, $duplicate);

        return ApiResponse::success(null, 'Clients merged successfully');
    }
}
