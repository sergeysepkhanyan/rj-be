<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\BookingFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBreakRequest;
use App\Http\Requests\UpdateBreakRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\BreakResource;
use App\Mail\GiftCardBalanceDeductedMail;
use App\Models\Booking;
use App\Models\GiftCardPurchase;
use App\Models\GiftCardUsage;
use App\Services\ApiResponse;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

    public function markNoShow(Booking $booking): JsonResponse
    {
        $updated = $this->bookingService->markBookingNoShow($booking);

        return ApiResponse::success([
            'booking' => new BookingResource($updated),
        ], 'Booking marked as no-show');
    }

    public function markPaid(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->type !== 'booking') {
            return ApiResponse::error(
                ['type' => __('messages.booking.only_bookings_can_be_marked_paid')],
                __('validation.failed'),
                422
            );
        }

        $paymentMethod = $request->input('paymentMethod', $request->input('payment_method'));
        $giftCardCode = $request->input('giftCardCode', $request->input('gift_card_code'));
        $tipAmount = (float) ($request->input('tipAmount', $request->input('tip_amount', 0)));

        // Validate payment methods (supports comma-separated for multiple, e.g. "gift_card,cash")
        $allowedMethods = ['cash', 'card', 'bank_transfer', 'gift_card'];
        if ($paymentMethod) {
            $methods = array_map('trim', explode(',', $paymentMethod));
            foreach ($methods as $method) {
                if (!in_array($method, $allowedMethods)) {
                    return ApiResponse::error(
                        ['paymentMethod' => 'Invalid payment method: ' . $method . '. Allowed: ' . implode(', ', $allowedMethods)],
                        __('validation.failed'),
                        422
                    );
                }
            }
            // Extract gift card code from payment methods if included
            if (in_array('gift_card', $methods) && empty($giftCardCode)) {
                // gift_card method selected but no code — will be validated below
            }
        }

        // Gift card is optional — can be combined with any payment method
        $giftCardAmountUsed = 0;
        if (!empty($giftCardCode)) {
            $purchase = GiftCardPurchase::where('code', $giftCardCode)
                ->where('status', 'active')
                ->first();

            if (!$purchase) {
                return ApiResponse::error(
                    ['giftCardCode' => 'Gift card not found or is not active.'],
                    __('validation.failed'),
                    422
                );
            }

            if ($purchase->isExpired()) {
                return ApiResponse::error(
                    ['giftCardCode' => 'This gift card has expired.'],
                    __('validation.failed'),
                    422
                );
            }

            if ($purchase->balance <= 0) {
                return ApiResponse::error(
                    ['giftCardCode' => 'This gift card has no remaining balance.'],
                    __('validation.failed'),
                    422
                );
            }

            $bookingTotal = (float) ($booking->final_price ?? $booking->price ?? 0) + max(0, $tipAmount);
            $giftCardAmountUsed = min((float) $purchase->balance, $bookingTotal);
        }

        $booking = DB::transaction(function () use ($booking, $paymentMethod, $giftCardCode, $tipAmount, $giftCardAmountUsed) {
            $booking = $this->bookingService->markBookingPaid($booking, [
                'paid_payment_method' => $paymentMethod,
                'gift_card_code' => !empty($giftCardCode) ? $giftCardCode : null,
                'tip_amount' => max(0, $tipAmount),
            ]);

            // Deduct from gift card balance and record usage
            if (!empty($giftCardCode) && $giftCardAmountUsed > 0) {
                $purchase = GiftCardPurchase::where('code', $giftCardCode)->lockForUpdate()->first();
                if ($purchase) {
                    $newBalance = max(0, (float) $purchase->balance - $giftCardAmountUsed);
                    $purchase->update([
                        'balance' => $newBalance,
                        ...($newBalance <= 0 ? ['status' => 'used'] : []),
                    ]);

                    GiftCardUsage::create([
                        'gift_card_purchase_id' => $purchase->id,
                        'amount_used' => $giftCardAmountUsed,
                        'used_for_type' => 'booking',
                        'used_for_id' => $booking->id,
                        'used_for_name' => $booking->customer_name ?? 'Booking #' . $booking->reference,
                        'used_for' => 'booking',
                        'notes' => 'Applied via mark-as-paid (remaining paid by ' . ($paymentMethod ?? 'unspecified') . ')',
                        'verified_by' => auth()->id(),
                    ]);

                    // Notify gift card buyer about balance deduction
                    if ($purchase->buyer_email) {
                        Mail::to($purchase->buyer_email)->queue(new GiftCardBalanceDeductedMail($purchase, $giftCardAmountUsed));
                    }
                }
            }

            return $booking;
        });

        return ApiResponse::success([
            'booking' => new BookingResource($booking),
        ], __('success.booking.marked_paid'));
    }

    public function validateGiftCard(Request $request): JsonResponse
    {
        $code = $request->input('code', $request->input('giftCardCode', ''));

        if (empty($code)) {
            return ApiResponse::error(
                ['code' => 'Gift card code is required.'],
                __('validation.failed'),
                422
            );
        }

        $purchase = GiftCardPurchase::where('code', $code)
            ->where('status', 'active')
            ->first();

        if (!$purchase) {
            return ApiResponse::error(
                ['code' => 'Gift card not found or is not active.'],
                __('validation.failed'),
                422
            );
        }

        if ($purchase->isExpired()) {
            return ApiResponse::error(
                ['code' => 'This gift card has expired.'],
                __('validation.failed'),
                422
            );
        }

        if ($purchase->balance <= 0) {
            return ApiResponse::error(
                ['code' => 'This gift card has no remaining balance.'],
                __('validation.failed'),
                422
            );
        }

        return ApiResponse::success([
            'giftCard' => [
                'code' => $purchase->code,
                'balance' => (float) $purchase->balance,
                'currency' => $purchase->currency ?? 'AED',
                'expiresAt' => $purchase->expires_at?->toISOString(),
            ],
        ], 'Gift card is valid.');
    }
}
