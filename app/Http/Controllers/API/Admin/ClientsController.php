<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddReferralRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ClientDetailResource;
use App\Models\User;
use App\Models\ClientNote;
use App\Models\Booking;
use App\Models\Order;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Get confirmed bookings count and total
        $confirmedBookings = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
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

        // Get confirmed orders count and total (paid, fulfilled, processing, shipped)
        $confirmedOrders = Order::where('user_id', $user->id)
            ->whereIn('status', ['paid', 'fulfilled', 'processing', 'shipped'])
            ->get();

        $ordersCount = $confirmedOrders->count();
        $ordersTotal = $confirmedOrders->sum('amount');

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
            ->with(['items.product', 'shippingAddress.country'])
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
                'payment_status' => $order->paid_at ? 'paid' : ($order->status === 'cancelled' ? 'cancelled' : 'unpaid'),
                'delivery_status' => $order->delivery_status,
                'created_at' => $order->created_at,
                'paid_at' => $order->paid_at,
                'items' => $order->items->map(function ($item) {
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

        return ApiResponse::success([
            'user' => new ClientResource($client),
        ], __('success.client.referral_added'));
    }
}
