<?php

namespace App\Http\Controllers\API;

use App\Filters\BookingFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\StoreBookingSelectionRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\ApiResponse;
use App\Services\BookingService;
use App\Services\BookingSelectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingsController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected BookingSelectionService $bookingSelectionService
    ) {}

    public function index(Request $request, BookingFilter $filter): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        $request->merge(['user_id' => auth()->id()]);

        $bookings = $this->bookingService->getPaginatedBookings($filter, $perPage, $page);

        return ApiResponse::success([
            'bookings' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page'    => $bookings->lastPage(),
                'per_page'     => $bookings->perPage(),
                'total'        => $bookings->total(),
            ],
            'links' => [
                'first' => $bookings->url(1),
                'last'  => $bookings->url($bookings->lastPage()),
                'prev'  => $bookings->previousPageUrl(),
                'next'  => $bookings->nextPageUrl(),
            ],
        ], __('success.booking.listed'));
    }

    public function availableSlots(AvailableSlotsRequest $request): JsonResponse
    {
        $slots = $this->bookingService->getAvailableSlots($request->all());

        return ApiResponse::success([
            'slots' => $slots,
        ], __('success.booking.slots_loaded'));
    }

    public function selectSlot(StoreBookingSelectionRequest $request): JsonResponse
    {
        $selection = $this->bookingSelectionService->createSelection($request->all());

        return ApiResponse::success([
            'selection' => $selection,
        ], __('success.booking.selection_added'));
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->createBooking($request->all());

        // Only send email immediately for pay_later bookings (immediately confirmed)
        // For pay_now bookings, email will be sent via webhook after payment success
        if ($booking->payment_mode === 'pay_later') {
            $this->bookingService->sendBookingConfirmation($booking);
        }

        return ApiResponse::success([
            'booking' => new BookingResource($booking)
        ], __('success.booking.created'));
    }

    /**
     * Create multiple bookings from services array, sharing a single order/payment.
     * Each service becomes its own booking for cleaner tracking.
     */
    public function storeBatch(StoreBookingRequest $request): JsonResponse
    {
        $result = $this->bookingService->createBatchBookings($request->all());

        // Only send email immediately for pay_later bookings
        if ($result['bookings'][0]->payment_mode === 'pay_later') {
            foreach ($result['bookings'] as $booking) {
                $this->bookingService->sendBookingConfirmation($booking);
            }
        }

        $order = $result['order'];
        $clientSecret = null;
        if ($order && $order->latestPayment && $order->latestPayment->provider === 'stripe') {
            $clientSecret = data_get($order->latestPayment->raw, 'client_secret');
        }

        return ApiResponse::success([
            'bookings' => BookingResource::collection($result['bookings']),
            'batchId' => $result['batchId'],
            'order' => $order ? [
                'id' => $order->id,
                'reference' => $order->reference,
                'total' => $order->total,
                'clientSecret' => $clientSecret,
            ] : null,
        ], __('success.booking.created'));
    }

    /**
     * Get a specific booking by ID
     *
     * @throws AuthorizationException
     */
    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking->load([
            'services.bookable',
            'services.master',
            'master',
            'order.latestPayment',
        ]);

        return ApiResponse::success([
            'booking' => new BookingResource($booking),
        ], __('success.booking.loaded'));
    }

    /**
     * @throws AuthorizationException
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        // Capture previous booking details before update
        $previousDate = $booking->date?->format('Y-m-d');
        $previousStartTime = $booking->start_time;
        $previousEndTime = $booking->end_time;
        $previousMasterId = $booking->master_id;
        // Get master IDs from services for multi-master bookings
        $previousServiceMasterIds = $booking->services->pluck('master_id')->sort()->values()->toArray();

        $updated = $this->bookingService->updateBooking(
            $booking,
            $request->all()
        );

        // Check if date/time/master changed and send notification
        $newDate = $updated->date?->format('Y-m-d');
        $newStartTime = $updated->start_time;
        $newEndTime = $updated->end_time;
        $newMasterId = $updated->master_id;
        $newServiceMasterIds = $updated->services->pluck('master_id')->sort()->values()->toArray();

        $isRescheduled = $previousDate !== $newDate
            || $previousStartTime !== $newStartTime
            || $previousEndTime !== $newEndTime
            || $previousMasterId !== $newMasterId
            || $previousServiceMasterIds !== $newServiceMasterIds;

        if ($isRescheduled) {
            $this->bookingService->sendBookingRescheduled(
                $updated,
                $previousDate,
                $previousStartTime,
                $previousEndTime
            );
        }

        return ApiResponse::success([
            'booking' => new BookingResource($updated),
        ], __('success.booking.updated'));
    }

    /**
     * @throws AuthorizationException
     */
    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $result = $this->bookingService->cancelBooking($booking, $request->all());
        $this->bookingService->sendBookingCancellation($result);

        return ApiResponse::success([
            'booking' => new BookingResource($result),
        ], __('success.booking.cancelled'));
    }
}


