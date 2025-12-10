<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\BookingFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\StoreBreakRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BreakResource;
use App\Models\Booking;
use App\Services\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingsController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request, BookingFilter $filter): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('per_page', 1);

        $bookings = $this->bookingService->getPaginatedBookings($filter, $perPage, $page);

        return ApiResponse::success([
            'bookings' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
            'links' => [
                'first' => $bookings->url(1),
                'last' => $bookings->url($bookings->lastPage()),
                'prev' => $bookings->previousPageUrl(),
                'next' => $bookings->nextPageUrl(),
            ],
        ], 'Bookings retrieved successfully');
    }

    public function storeBreak(StoreBreakRequest $request): JsonResponse
    {
        try {
            $data = $request->only('date', 'start_time', 'end_time', 'master_id');

            $break = $this->bookingService->createBreak($data);

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
