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
        $this->bookingService->sendBookingConfirmation($booking);
        return ApiResponse::success([
            'booking' => new BookingResource($booking)
        ], __('success.booking.created'));
    }

    /**
     * @throws AuthorizationException
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('update', $booking);

        $updated = $this->bookingService->updateBooking(
            $booking,
            $request->all()
        );

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

        $result = $this->bookingService->cancelBooking($booking, $request->validated());
        $this->bookingService->sendBookingCancellation($result);

        return ApiResponse::success([
            'booking' => new BookingResource($result),
        ], __('success.booking.cancelled'));
    }
}


