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

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $page    = (int) $request->get('page', 1);

        $clients = $this->userService->getPaginatedClients($perPage, $page);

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
            ->with(['master', 'services.bookable', 'services.master'])
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

            $results[] = [
                'id' => $user->id,
                'type' => 'user',
                'name' => $fullName,
                'phone' => $user->mobile,
                'email' => $user->email,
                'discount' => $discount,
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
}
