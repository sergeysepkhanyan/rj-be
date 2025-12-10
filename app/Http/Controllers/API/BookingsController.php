<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailableSlotsRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingsController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function availableSlots(AvailableSlotsRequest $request): JsonResponse
    {
        $slots = $this->bookingService->getAvailableSlots($request->all());

        return ApiResponse::success([
            'slots' => $slots,
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->bookingService->createBooking($request->all());
            return ApiResponse::success([
                'booking'    => new BookingResource($booking)
            ], 'Booking created successfully.');
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error();
        }
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        try {
            $updated = $this->bookingService->updateBooking(
                $booking,
                $request->all()
            );

            return ApiResponse::success([
                'booking' => new BookingResource($updated),
            ], 'Booking updated successfully');
        }  catch (\Throwable $e) {
            report($e);
            return ApiResponse::error();
        }
    }
}

