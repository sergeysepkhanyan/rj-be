<?php

namespace App\Http\Controllers\API;

use App\Filters\BookingFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingsController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
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
        ], 'My bookings retrieved successfully');
    }

    public function availableSlots(AvailableSlotsRequest $request): JsonResponse
    {
        $slots = $this->bookingService->getAvailableSlots($request->all());

        return ApiResponse::success([
            'slots' => $slots,
        ]);
    }

    /**
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->createBooking($request->all());
        return ApiResponse::success([
            'booking'    => new BookingResource($booking)
        ], 'Booking created successfully.');
    }

    /**
     */
    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $updated = $this->bookingService->updateBooking(
            $booking,
            $request->all()
        );

        return ApiResponse::success([
            'booking' => new BookingResource($updated),
        ], 'Booking updated successfully');
    }
}

