<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\BookingFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBreakRequest;
use App\Http\Requests\UpdateBreakRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BreakResource;
use App\Models\Booking;
use App\Services\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingsController extends Controller
{
    public function __construct(protected BookingService $bookingService) {}

    public function index(Request $request, BookingFilter $filter): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        $bookings = $this->bookingService->getPaginatedBookings($filter, $perPage, $page);

        $items = $bookings->getCollection()->map(function ($item) {
            return $item->type === 'break' 
                ? new BreakResource($item)
                : new BookingResource($item);
        });

        return ApiResponse::success([
            'bookings' => $items,
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
        ], __('success.booking.list'));
    }

    public function storeBreak(StoreBreakRequest $request): JsonResponse
    {
        $data = $request->only(['date', 'start_time', 'end_time', 'master_id', 'timezone', 'notes']);

        $break = $this->bookingService->createBreak($data);

        return ApiResponse::success([
            'break' => new BreakResource($break),
        ], __('success.break.created'));
    }

    public function updateBreak(UpdateBreakRequest $request, Booking $booking): JsonResponse
    {
        $data = $request->only(['date', 'start_time', 'end_time', 'timezone', 'notes']);

        $break = $this->bookingService->updateBreak($booking, $data);

        return ApiResponse::success([
            'break' => new BreakResource($break),
        ], __('success.break.updated'));
    }

    public function deleteBreak(Booking $booking): JsonResponse
    {
        if ($booking->type !== 'break') {
            return ApiResponse::error(
                ['type' => __('messages.break.not_a_break')],
                __('validation.failed'),
                422
            );
        }

        $this->bookingService->deleteBreak($booking);

        return ApiResponse::success([
            'deleted' => true,
        ], __('success.break.deleted'));
    }

    public function markPaid(Booking $booking): JsonResponse
    {
        if ($booking->type !== 'booking') {
            return ApiResponse::error(
                ['type' => __('messages.booking.only_bookings_can_be_marked_paid')],
                __('validation.failed'),
                422
            );
        }

        $booking = $this->bookingService->markBookingPaid($booking);

        return ApiResponse::success([
            'booking' => new BookingResource($booking),
        ], __('success.booking.marked_paid'));
    }

}
