<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\BookingFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\StoreBreakRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BreakResource;
use App\Services\ApiResponse;
use App\Services\UserBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingsController extends Controller
{
    protected UserBookingService $userBookingService;

    public function __construct(UserBookingService $userBookingService)
    {
        $this->userBookingService = $userBookingService;
    }

    public function index(Request $request, BookingFilter $filter): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('per_page', 1);

        $bookings = $this->userBookingService->getPaginatedBookings($filter, $perPage, $page);

        return BookingResource::collection($bookings)
            ->additional([
                'meta' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                ],
                'filters' => $request->only(['master_id', 'date', 'search']),
            ]);
    }

    public function storeAppointment(StoreAppointmentRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $booking = $this->userBookingService->createBooking($data);
            if (!$booking) {
                return ApiResponse::error(
                    ['message' => 'Booking overlaps with existing booking or invalid time.'],
                    'Validation failed', 422
                );
            }
            return ApiResponse::success([
                'booking' => new BookingResource($booking),
            ], 'Booking created successfully');
        } catch (\Throwable $e) {
            return ApiResponse::error();
        }
    }

    public function storeBreak(StoreBreakRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $break = $this->userBookingService->createBreak($data);

            if (!$break) {
                return ApiResponse::error(
                    ['message' => 'Break overlaps with existing booking or invalid time.'],
                    'Validation failed', 422
                );
            }
            return ApiResponse::success([
                'break' => new BreakResource($break),
            ], 'Break created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }

    }

}
