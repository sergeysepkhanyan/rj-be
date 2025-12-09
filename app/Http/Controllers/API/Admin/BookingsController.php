<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\BookingFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\StoreBreakRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BreakResource;
use App\Models\Booking;
use App\Models\UserBooking;
use App\Services\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingsController extends Controller
{
    protected BookingService $userBookingService;

    public function __construct(BookingService $userBookingService)
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
            $data = $request->all();
            $data = array_intersect_key($data, array_flip((new Booking)->getFillable()));

            $booking = $this->userBookingService->createBooking($data);
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
            $data = $request->only('date', 'start_time', 'end_time', 'master_id');

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
