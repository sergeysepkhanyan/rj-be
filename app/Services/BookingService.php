<?php

namespace App\Services;

use App\Filters\BookingFilter;
use App\Mail\BookingCancelledAdminNotificationMail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingRescheduledAdminNotificationMail;
use App\Mail\BookingRescheduledMail;
use App\Mail\NewBookingAdminNotificationMail;
use App\Models\Booking;
use App\Models\User;
use App\Models\Weekday;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\BookingSelectionRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;
use App\Repositories\Interfaces\WorkingHourRepositoryInterface;
use App\Support\VatCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected BookingSelectionRepositoryInterface $bookingSelectionRepository,
        protected SubServiceRepositoryInterface $subServiceRepository,
        protected SubServiceItemRepositoryInterface $subServiceItemRepository,
        protected WorkingHourRepositoryInterface $workingHourRepository,
        protected MasterAssignmentService $masterAssignmentService,
        protected OrderService $orderService,
        protected PaymentService $paymentService,
        protected PaymentRepositoryInterface $paymentRepository,
        protected ReferralRewardService $referralRewardService,
        protected LoyaltyService $loyaltyService,
        protected ServicePackageService $servicePackageService,
        protected CustomerService $customerService,
    ) {}

    public function getAllBooking()
    {
        return $this->bookingRepository->all();
    }

    public function getBookingById($id)
    {
        return $this->bookingRepository->find($id);
    }

    public function deleteBreak(Booking $booking)
    {
        return $this->bookingRepository->delete($booking);
    }

    public function getPaginatedBookings(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->bookingRepository->paginateWithFilter($filter, $perPage, $page);
    }

    public function createBreak(array $data): ?Booking
    {
        $tz = $data['timezone'] ?? 'UTC';

        $date = trim($data['date']);
        $startTime = trim($data['start_time']);
        $endTime = trim($data['end_time']);

        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}", $tz);
        $end = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}", $tz);

        if ($end->lte($start)) {
            $this->throwValidation(
                ['endTime' => __('validation.break.end_after_start')],
                'validation.failed'
            );
        }

        $masterId = (int) ($data['master_id'] ?? 0);
        if ($masterId && $this->isMasterOffOnDate($masterId, $date, $tz)) {
            $this->throwValidation(
                ['masterId' => __('validation.booking.master_day_off')],
                'validation.failed'
            );
        }

        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId: $masterId,
            date: $date,
            startTime: $start->format('H:i'),
            endTime: $end->format('H:i'),
            timezone: $tz,
        );

        if ($hasOverlap) {
            $this->throwValidation(
                ['masterId' => __('validation.break.master_unavailable')],
                'validation.failed'
            );
        }

        $breakData = [
            'user_id' => null,
            'master_id' => $masterId,
            'type' => 'break',
            'status' => 'confirmed',
            'date' => $date,
            'timezone' => $tz,
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'duration' => $start->diffInMinutes($end),
            'duration_unit' => 'minutes',
            'customer_name' => 'Break',
            'customer_email' => null,
            'customer_phone' => null,
            'notes' => $data['notes'] ?? null,
        ];

        return $this->bookingRepository->create($breakData);
    }

    public function updateBreak(Booking $booking, array $data): Booking
    {
        if ($booking->type !== 'break') {
            $this->throwValidation([], 'booking.break_only_update', []);
        }

        $tz = $data['timezone'] ?? $booking->timezone ?? 'UTC';

        $date = isset($data['date']) ? trim($data['date']) : $booking->date;

        $startTime = isset($data['start_time'])
            ? trim($data['start_time'])
            : $booking->start_time;

        $endTime = isset($data['end_time'])
            ? trim($data['end_time'])
            : $booking->end_time;

        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}", $tz);
        $end = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}", $tz);

        if ($end->lte($start)) {
            $this->throwValidation(['endTime' => __('validation.break.end_after_start')], 'validation.failed');
        }

        if ($this->isMasterOffOnDate((int) $booking->master_id, $date, $tz)) {
            $this->throwValidation(['masterId' => __('validation.booking.master_day_off')], 'validation.failed');
        }

        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId: $booking->master_id,
            date: $date,
            startTime: $start->format('H:i'),
            endTime: $end->format('H:i'),
            excludeBookingId: $booking->id,
            timezone: $tz
        );

        if ($hasOverlap) {
            $this->throwValidation(['masterId' => __('validation.break.master_unavailable')], 'validation.failed');
        }

        $updateData = [
            'date' => $date,
            'timezone' => $tz,
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'duration' => $start->diffInMinutes($end),
            'duration_unit' => 'minutes',
        ];

        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'];
        }

        return $this->bookingRepository->update($booking, $updateData);
    }

    public function getAvailableSlots(array $data): array
    {
        $tz = $data['timezone'] ?? 'UTC';

        $anyMaster = (bool) ($data['anyMaster'] ?? $data['any_master'] ?? false);
        $masterId = (int) ($data['master_id'] ?? $data['masterId'] ?? 0);
        $date = trim($data['date'] ?? '');
        $subserviceId = $data['sub_service_id'] ?? null;
        $subserviceItemId = $data['sub_service_item_id'] ?? null;
        $excludeBookingId = $data['exclude_booking_id'] ?? $data['excludeBookingId'] ?? null;
        $excludeBookingId = $excludeBookingId ? (int) $excludeBookingId : null;

        if (! $date) {
            $this->throwValidation(
                ['date' => __('validation.available_slots.date_required')],
                'validation.failed'
            );
        }

        if (! $masterId && ! $anyMaster) {
            // Neither a specific master nor "any master" was requested
            $anyMaster = true;
        }

        if ($anyMaster || ! $masterId) {
            $candidateMasterIds = $this->resolveCandidateMasterIds(
                subserviceId: $subserviceId,
                subserviceItemId: $subserviceItemId
            );

            if ($candidateMasterIds->isEmpty()) {
                return [];
            }

            if ($this->isMasterOffOnDate((int) $candidateMasterIds->first(), $date, $tz)) {
                // Don't early return: other masters might still be available.
            }

            $durationMinutes = $this->resolveDurationMinutes($subserviceId, $subserviceItemId);

            $hours = $this->getWorkingHours($date, $tz);
            if ($hours['is_closed']) {
                return [];
            }

            $workStart = $hours['start'];
            $workEnd = $hours['end'];

            $serviceBusy = collect();
            if ($subserviceId) {
                $serviceBusy = $this->bookingRepository->getBusyForServiceOnDate(
                    'App\\Models\\SubService',
                    $subserviceId,
                    $date,
                    $excludeBookingId
                );
            } elseif ($subserviceItemId) {
                $serviceBusy = $this->bookingRepository->getBusyForServiceOnDate(
                    'App\\Models\\SubServiceItem',
                    $subserviceItemId,
                    $date,
                    $excludeBookingId
                );
            }

            $uniqueSlots = [];
            $slotKeys = [];

            foreach ($candidateMasterIds as $mid) {
                if (! $mid) {
                    continue;
                }

                if ($this->isMasterOffOnDate((int) $mid, $date, $tz)) {
                    continue;
                }

                $masterBusy = $this->bookingRepository->getBusyForMasterOnDate((int) $mid, $date, $excludeBookingId);
                $busy = $masterBusy->merge($serviceBusy);

                $slots = $this->buildSlots($date, $workStart, $workEnd, $busy, $durationMinutes, $tz);

                foreach ($slots as $slot) {
                    $key = $slot['start'].'-'.$slot['end'];
                    if (isset($slotKeys[$key])) {
                        continue;
                    }

                    $slotKeys[$key] = true;
                    $uniqueSlots[] = $slot;
                }
            }

            usort($uniqueSlots, fn ($a, $b) => strcmp($a['start'], $b['start']));

            return $uniqueSlots;
        }

        if ($this->isMasterOffOnDate($masterId, $date, $tz)) {
            return [];
        }

        $durationMinutes = $this->resolveDurationMinutes($subserviceId, $subserviceItemId);

        $hours = $this->getWorkingHours($date, $tz);
        if ($hours['is_closed']) {
            return [];
        }

        $workStart = $hours['start'];
        $workEnd = $hours['end'];

        $masterBusy = $this->bookingRepository->getBusyForMasterOnDate($masterId, $date, $excludeBookingId);

        $serviceBusy = collect();
        if ($subserviceId) {
            $serviceBusy = $this->bookingRepository->getBusyForServiceOnDate(
                'App\\Models\\SubService',
                $subserviceId,
                $date,
                $excludeBookingId
            );
        } elseif ($subserviceItemId) {
            $serviceBusy = $this->bookingRepository->getBusyForServiceOnDate(
                'App\\Models\\SubServiceItem',
                $subserviceItemId,
                $date,
                $excludeBookingId
            );
        }

        $busy = $masterBusy->merge($serviceBusy);

        return $this->buildSlots($date, $workStart, $workEnd, $busy, $durationMinutes, $tz);
    }

    protected function resolveCandidateMasterIds(?int $subserviceId, ?int $subserviceItemId): \Illuminate\Support\Collection
    {
        if ($subserviceId) {
            $sub = \App\Models\SubService::query()
                ->with('masters:id')
                ->find($subserviceId);

            return collect($sub?->masters?->pluck('id')->all() ?? []);
        }

        if ($subserviceItemId) {
            $item = \App\Models\SubServiceItem::query()
                ->with('subService.masters:id')
                ->find($subserviceItemId);

            return collect($item?->subService?->masters?->pluck('id')->all() ?? []);
        }

        return collect();
    }

    protected function resolveDurationMinutes(?int $subserviceId, ?int $subserviceItemId): int
    {
        if ($subserviceItemId) {
            $item = $this->subServiceItemRepository->find($subserviceItemId);

            return (int) ($item->duration ?? 0);
        }

        if ($subserviceId) {
            $sub = $this->subServiceRepository->find($subserviceId);

            return (int) ($sub->duration ?? 0);
        }

        return 30;
    }

    protected function buildSlots(
        string $date,
        string $workStart,
        string $workEnd,
        Collection $busy,
        int $durationMinutes,
        string $tz
    ): array {
        $slots = [];

        $dayStart = Carbon::createFromFormat('Y-m-d H:i', "$date $workStart", $tz);
        $dayEnd = Carbon::createFromFormat('Y-m-d H:i', "$date $workEnd", $tz);

        // 00:00 means midnight end of day — advance to next day
        if ($workEnd === '00:00') {
            $dayEnd->addDay();
        }
        $now = Carbon::now($tz);

        if ($dayEnd->lt($now->copy()->startOfDay())) {
            return [];
        }

        $busyIntervals = $busy->map(function ($row) use ($tz) {
            $rowTz = $row->timezone ?? 'UTC';

            $rowDate = is_string($row->date)
                ? trim(substr($row->date, 0, 10))
                : Carbon::parse($row->date, $rowTz)->toDateString();

            $startStr = trim((string) $row->start_time);
            $endStr = trim((string) $row->end_time);

            if (strlen($startStr) === 5) {
                $startStr .= ':00';
            }
            if (strlen($endStr) === 5) {
                $endStr .= ':00';
            }

            $startLocal = Carbon::createFromFormat('Y-m-d H:i:s', $rowDate.' '.$startStr, $rowTz);
            $endLocal = Carbon::createFromFormat('Y-m-d H:i:s', $rowDate.' '.$endStr, $rowTz);

            return [
                'start' => $startLocal->copy()->setTimezone($tz),
                'end' => $endLocal->copy()->setTimezone($tz),
            ];
        });

        $cursor = $dayStart->copy();

        if ($dayStart->isSameDay($now)) {
            $roundedNow = $now->copy()->setSecond(0);

            $mod = $roundedNow->minute % 5;
            if ($mod !== 0) {
                $roundedNow->addMinutes(5 - $mod);
            }

            if ($roundedNow->gt($cursor)) {
                $cursor = $roundedNow;
            }
        }

        while ($cursor->copy()->addMinutes($durationMinutes) <= $dayEnd) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes($durationMinutes);

            if ($dayStart->isSameDay($now) && $slotStart->lte($now)) {
                $cursor->addMinutes(5);

                continue;
            }

            $overlaps = $busyIntervals->contains(function ($interval) use ($slotStart, $slotEnd) {
                return $slotStart < $interval['end'] && $slotEnd > $interval['start'];
            });

            if (! $overlaps) {
                $slots[] = [
                    'start' => $slotStart->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                ];
            }

            $cursor->addMinutes(5);
        }

        return $slots;
    }

    public function createBooking(array $data): Booking
    {
        $user = auth()->user();

        // If admin is creating a booking on behalf of a client, use the client's user
        $clientUserId = $data['client_user_id'] ?? $data['clientUserId'] ?? null;
        if ($clientUserId && $user && in_array(optional($user->role)->slug, ['admin', 'superadmin'])) {
            $clientUser = User::find($clientUserId);
            if ($clientUser) {
                $user = $clientUser;
            }
        }

        $tz = $data['timezone'] ?? 'UTC';
        $date = trim($data['date'] ?? '');

        $rawServices = $data['services'] ?? [];
        if (! is_array($rawServices) || count($rawServices) === 0) {
            $this->throwValidation(
                ['services' => __('validation.booking.services_required')],
                'validation.failed'
            );
        }

        $rawServices = $this->masterAssignmentService->assignAndValidateMasters(
            date: $date,
            tz: $tz,
            services: $rawServices
        );

        $segments = $this->buildServiceSegmentsFromRequest($date, $rawServices, $tz);

        $pricing = $this->buildPricingData([
            ...$data,
            'services' => $this->normalizeServicesForPricing($segments),
        ]);

        return DB::transaction(function () use ($data, $user, $tz, $segments, $pricing) {

            // Re-validate availability under a pessimistic lock now that we are
            // inside the transaction — closes the race between the pre-flight
            // master-assignment check and the actual insert (double-booking).
            $this->assertSegmentsAvailable($segments, $tz);

            $firstSegment = $segments[0];
            $lastSegment = $segments[count($segments) - 1];
            $bookingStart = substr($firstSegment['start_time'], 0, 5);
            $bookingEnd = substr($lastSegment['end_time'], 0, 5);
            $uniqueMasters = collect($segments)->pluck('master_id')->unique()->values();
            $uniqueDates = collect($segments)->pluck('date')->unique();

            if ($uniqueDates->count() > 1) {
                $totalDuration = collect($segments)->sum('duration_minutes');
            } else {
                $totalDuration = $this->calculateDuration($bookingStart, $bookingEnd);
            }

            $bookingData = [
                'user_id' => $user?->id,
                'type' => 'booking',
                'reference' => $this->makeBookingReference(),
                'date' => $firstSegment['date'],
                'timezone' => $tz,
                'start_time' => $bookingStart,
                'end_time' => $bookingEnd,
                'duration' => $totalDuration,
                'duration_unit' => 'minutes',

                'price' => $pricing['total_price'],
                'discount_type' => $pricing['discount_type'],
                'discount_value' => $pricing['discount_value'],
                'discount_label' => $pricing['discount_label'],
                'final_price' => $pricing['final_price'],
                'payment_mode' => $pricing['payment_mode'],
                'payment_status' => $pricing['payment_status'],
                'status' => $pricing['payment_mode'] === 'pay_now' ? 'pending_payment' : 'confirmed',
                'expires_at' => $pricing['payment_mode'] === 'pay_now'
                    ? now()->addMinutes((int) config('payment.booking_hold_minutes', 10))
                    : null,
                'customer_name' => $data['customer_name'] ?? $data['customerName'],
                'customer_phone' => $data['customer_phone'] ?? $data['customerPhone'],
                'customer_email' => $data['customer_email'] ?? $data['customerEmail'],
                'notes' => $data['notes'] ?? null,

                'master_id' => $uniqueMasters->count() === 1 ? (int) $uniqueMasters->first() : null,
            ];

            // Handle complimentary gift booking
            $isGiftBooking = false;
            $complimentaryRewardId = $data['complimentary_reward_id'] ?? null;
            if ($complimentaryRewardId && $user) {
                $reward = \App\Models\ComplimentaryReward::where('id', $complimentaryRewardId)
                    ->where('user_id', $user->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();

                if ($reward) {
                    $isGiftBooking = true;
                    $bookingData['is_complimentary'] = true;
                    $bookingData['complimentary_reward_id'] = $reward->id;
                    $bookingData['price'] = 0;
                    $bookingData['final_price'] = 0;
                    $bookingData['discount_type'] = 'none';
                    $bookingData['discount_value'] = null;
                    $bookingData['discount_label'] = null;
                    $bookingData['payment_mode'] = 'pay_later';
                    $bookingData['payment_status'] = 'gift';
                    $bookingData['status'] = 'confirmed';
                    $bookingData['expires_at'] = null;
                }
            }

            // Handle service package booking
            $isPackageBooking = false;
            $servicePackagePurchaseId = $data['service_package_purchase_id'] ?? null;
            $servicePackageItemId = $data['service_package_item_id'] ?? null;
            if ($servicePackagePurchaseId && $servicePackageItemId && $user) {
                $packagePurchase = \App\Models\ServicePackagePurchase::where('id', $servicePackagePurchaseId)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($packagePurchase) {
                    $isPackageBooking = true;
                    $bookingData['service_package_purchase_id'] = $packagePurchase->id;
                    $bookingData['is_package_booking'] = true;
                    $bookingData['price'] = 0;
                    $bookingData['final_price'] = 0;
                    $bookingData['discount_type'] = 'none';
                    $bookingData['discount_value'] = null;
                    $bookingData['discount_label'] = null;
                    $bookingData['payment_mode'] = 'pay_later';
                    $bookingData['payment_status'] = 'package';
                    $bookingData['status'] = 'confirmed';
                    $bookingData['expires_at'] = null;
                }
            }

            $booking = $this->bookingRepository->create($bookingData);

            foreach ($segments as $i => $seg) {
                $this->attachServiceToBookingWithSegment($booking, $seg, $i + 1);
            }

            $this->clearSelectionsAfterBooking($user?->id, $data['guest_session_id'] ?? null);

            $this->createLeadFromBooking($booking);

            // Assign referrer if provided
            if (!empty($data['referrer_user_id'])) {
                $this->referralRewardService->assignReferrer($booking, (int) $data['referrer_user_id']);
            }

            // Handle gift booking — redeem reward and skip payment
            if ($isGiftBooking && isset($reward)) {
                $this->referralRewardService->redeemReward($reward, $booking);
                $order = $this->orderService->createForBooking($booking, 'pay_later');
                // Mark order as gift
                $order->update(['status' => 'gift']);
                return $booking->fresh()->load(['services.bookable', 'order.latestPayment', 'bookingReferral.referrer']);
            }

            // Handle package booking — redeem visit and skip payment
            if ($isPackageBooking && isset($packagePurchase) && $servicePackageItemId) {
                $this->servicePackageService->redeemVisit($packagePurchase, (int) $servicePackageItemId, $booking);
                $order = $this->orderService->createForBooking($booking, 'pay_later');
                $order->update([
                    'status' => 'gift',
                    'meta' => array_merge($order->meta ?? [], ['is_package_booking' => true]),
                ]);
                return $booking->fresh()->load(['services.bookable', 'order.latestPayment', 'bookingReferral.referrer']);
            }

            $order = $this->orderService->createForBooking($booking, $booking->payment_mode);
            if ($booking->payment_mode === 'pay_later') {
                $this->bookingRepository->update($booking, ['status' => 'confirmed']);

                // Complete referral for pay_later bookings (immediately confirmed)
                $this->referralRewardService->completeReferral($booking);

                // Check loyalty tier upgrade
                if ($booking->user_id) {
                    $loyaltyUser = User::find($booking->user_id);
                    if ($loyaltyUser) {
                        $this->loyaltyService->checkAndUpgradeUser($loyaltyUser);
                    }
                }

                return $booking->fresh()->load(['services.bookable', 'order.latestPayment', 'bookingReferral.referrer']);
            }

            // Handle gift card for pay_now bookings
            $giftCardCode = $data['gift_card_code'] ?? $data['giftCardCode'] ?? null;
            if ($giftCardCode) {
                $purchase = \App\Models\GiftCardPurchase::where('code', $giftCardCode)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($purchase && !$purchase->isExpired() && $purchase->balance > 0) {
                    $total = (float) $order->amount;
                    $giftCardAmountApplied = min((float) $purchase->balance, $total);
                    $newBalance = max(0, (float) $purchase->balance - $giftCardAmountApplied);

                    $purchase->update([
                        'balance' => $newBalance,
                        ...($newBalance <= 0 ? ['status' => 'used'] : []),
                    ]);

                    \App\Models\GiftCardUsage::create([
                        'gift_card_purchase_id' => $purchase->id,
                        'amount_used' => $giftCardAmountApplied,
                        'used_for_type' => 'booking',
                        'used_for_id' => $booking->id,
                        'used_for_name' => $booking->services->first()?->bookable?->name ?? 'Booking #' . $booking->id,
                        'used_for' => 'booking',
                        'notes' => 'Applied at online booking checkout',
                        'verified_by' => $user?->id,
                    ]);

                    $order->update([
                        'meta' => array_merge($order->meta ?? [], [
                            'gift_card_code' => $giftCardCode,
                            'gift_card_amount' => $giftCardAmountApplied,
                        ]),
                    ]);

                    if ($purchase->buyer_email) {
                        \Illuminate\Support\Facades\Mail::to($purchase->buyer_email)
                            ->queue(new \App\Mail\GiftCardBalanceDeductedMail($purchase, $giftCardAmountApplied));
                    }

                    // If gift card fully covers the booking
                    if ($giftCardAmountApplied >= $total) {
                        $order->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'amount' => 0,
                        ]);

                        $booking->update([
                            'status' => 'confirmed',
                            'payment_status' => 'paid',
                            'expires_at' => null,
                        ]);

                        if ($booking->batch_id) {
                            $this->markBatchBookingsPaid($booking->batch_id);
                        }

                        $this->sendBookingConfirmation($booking);
                        $this->referralRewardService->completeReferral($booking);

                        if ($booking->user_id) {
                            $loyaltyUser = User::find($booking->user_id);
                            if ($loyaltyUser) {
                                $this->loyaltyService->checkAndUpgradeUser($loyaltyUser);
                            }
                        }

                        return $booking->fresh()->load(['services.bookable', 'order.latestPayment', 'bookingReferral.referrer']);
                    }

                    // Partial coverage - reduce order amount
                    $remainingAmount = $total - $giftCardAmountApplied;
                    $order->update(['amount' => $remainingAmount]);
                }
            }

            $provider = $data['payment_provider'] ?? $data['paymentProvider'] ?? 'stripe';
            $this->paymentService->startStripePaymentIntent($order, $booking);

            return $booking->load(['services.bookable', 'services.master', 'master', 'order.latestPayment', 'bookingReferral.referrer']);
        });
    }

    public function updateBooking(Booking $booking, array $data): Booking
    {
        $tz = $data['timezone'] ?? $booking->timezone ?? 'UTC';
        $date = trim($data['date'] ?? ($booking->date?->format('Y-m-d') ?? ''));

        $data['payment_mode'] = $booking->payment_mode;
        $data['paymentMode'] = $booking->payment_mode;

        $rawServices = $data['services'] ?? [];

        $rawServices = $this->masterAssignmentService->assignAndValidateMasters(
            date: $date,
            tz: $tz,
            services: $rawServices,
            excludeBookingId: $booking->id
        );

        $segments = $this->buildServiceSegmentsFromRequest($date, $rawServices, $tz);

        $pricing = $this->buildPricingData([
            ...$data,
            'services' => $this->normalizeServicesForPricing($segments),
        ]);

        return DB::transaction(function () use ($booking, $data, $tz, $segments, $pricing) {
            $this->assertSegmentsAvailable($segments, $tz, $booking->id);

            $firstSegment = $segments[0];
            $lastSegment = $segments[count($segments) - 1];
            $bookingStart = substr($firstSegment['start_time'], 0, 5);
            $bookingEnd = substr($lastSegment['end_time'], 0, 5);

            $uniqueMasters = collect($segments)->pluck('master_id')->unique()->values();
            $uniqueDates = collect($segments)->pluck('date')->unique();

            if ($uniqueDates->count() > 1) {
                $totalDuration = collect($segments)->sum('duration_minutes');
            } else {
                $totalDuration = $this->calculateDuration($bookingStart, $bookingEnd);
            }

            $updateData = [
                'date' => $firstSegment['date'],
                'timezone' => $tz,
                'start_time' => $bookingStart,
                'end_time' => $bookingEnd,
                'duration' => $totalDuration,
                'duration_unit' => 'minutes',

                'price' => $pricing['total_price'],
                'discount_type' => $pricing['discount_type'],
                'discount_value' => $pricing['discount_value'],
                'discount_label' => $pricing['discount_label'],
                'final_price' => $pricing['final_price'],
                'payment_mode' => $pricing['payment_mode'],

                'customer_name' => $data['customer_name'] ?? $data['customerName'] ?? $booking->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $data['customerPhone'] ?? $booking->customer_phone,
                'customer_email' => $data['customer_email'] ?? $data['customerEmail'] ?? $booking->customer_email,
                'notes' => $data['notes'] ?? $booking->notes,

                'master_id' => $uniqueMasters->count() === 1 ? (int) $uniqueMasters->first() : null,
            ];

            if ($booking->payment_status === 'unpaid' || $booking->payment_status === null) {
                $updateData['payment_status'] = $pricing['payment_status'];
            }

            $booking->update($updateData);

            $booking->services()->delete();

            foreach ($segments as $i => $seg) {
                $this->attachServiceToBookingWithSegment($booking, $seg, $i + 1);
            }

            $existingOrder = $booking->order()->first();
            if (
                $existingOrder
                && $existingOrder->status !== 'paid'
                && $existingOrder->status !== 'refunded'
                && (float) $existingOrder->amount !== (float) $pricing['final_price']
            ) {
                $existingOrder->update([
                    'amount' => $pricing['final_price'],
                    'meta' => array_merge($existingOrder->meta ?? [], [
                        'amount_resynced_at' => now()->toIso8601String(),
                        'amount_resync_reason' => 'booking_services_changed',
                    ]),
                ]);

                $pendingPayment = $existingOrder->payments()
                    ->whereNotIn('status', ['paid', 'refunded'])
                    ->latest('id')
                    ->first();
                if ($pendingPayment) {
                    $this->paymentRepository->update($pendingPayment, [
                        'amount' => $pricing['final_price'],
                    ]);
                }
            }

            return $booking->load(['services.bookable', 'services.master', 'bookingReferral.referrer']);
        });
    }

    protected function buildServiceSegmentsFromRequest(string $date, array $rawServices, string $tz): array
    {
        $segments = [];

        foreach (collect($rawServices)->values() as $index => $s) {
            $serviceType = $s['service_type'] ?? $s['serviceType'] ?? null;
            $serviceId = $s['service_id'] ?? $s['serviceId'] ?? null;
            $masterId = $s['master_id'] ?? $s['masterId'] ?? null;

            $startTime = $s['start_time'] ?? $s['startTime'] ?? null;
            $endTime = $s['end_time'] ?? $s['endTime'] ?? null;
            $serviceDate = $s['date'] ?? $date;

            if (! $serviceType || ! $serviceId) {
                $this->throwValidation(
                    ["services.$index.serviceId" => __('validation.booking.service_type_and_id_required')],
                    'validation.failed'
                );
            }

            if (! $masterId) {
                $this->throwValidation(
                    ["services.$index.masterId" => __('validation.booking.master_required_when_any_false')],
                    'validation.failed'
                );
            }

            if (! $startTime || ! $endTime) {
                $this->throwValidation(
                    [
                        "services.$index.startTime" => __('validation.booking.service_start_required'),
                        "services.$index.endTime" => __('validation.booking.service_end_required'),
                    ],
                    'validation.failed'
                );
            }

            $serviceable = $this->resolveServiceable((string) $serviceType, (int) $serviceId);
            $expectedMinutes = (int) ($serviceable->duration ?? 0);

            $start = $this->parseTimeToCarbon($serviceDate, (string) $startTime, $tz);
            $end = $this->parseTimeToCarbon($serviceDate, (string) $endTime, $tz);

            if ($end->lte($start)) {
                $this->throwValidation(
                    ["services.$index.endTime" => __('validation.booking.end_after_start')],
                    'validation.failed'
                );
            }

            $actualMinutes = (int) $start->diffInMinutes($end);

            if ($expectedMinutes <= 0) {
                $this->throwValidation(
                    ["services.$index.serviceId" => __('validation.booking.invalid_duration_config')],
                    'validation.failed'
                );
            }

            if ($actualMinutes !== $expectedMinutes) {
                $this->throwValidation(
                    ["services.$index.serviceId" => __('validation.booking.duration_mismatch', [
                        'expected' => $expectedMinutes,
                        'actual' => $actualMinutes,
                    ])],
                    'validation.failed'
                );
            }

            $basePrice = method_exists($serviceable, 'getFinalPrice')
                ? (float) $serviceable->getFinalPrice()
                : (float) ($serviceable->price ?? 0);
            $vat = VatCalculator::breakdown($basePrice, true);

            $isAnyMaster = (bool) ($s['any_master'] ?? $s['anyMaster'] ?? false);

            $segments[] = [
                'master_id' => (int) $masterId,
                'is_any_master' => $isAnyMaster,
                'bookable_type' => get_class($serviceable),
                'bookable_id' => $serviceable->id,
                'duration_minutes' => $expectedMinutes,
                'price' => (float) $vat['final_price'],
                'base_price' => (float) $vat['base_price'],
                'vat_enabled' => (bool) $vat['vat_enabled'],
                'vat_rate' => (float) $vat['vat_rate'],
                'vat_amount' => (float) $vat['vat_amount'],
                'final_price' => (float) $vat['final_price'],
                'sort_order' => $s['sort_order'] ?? $s['sortOrder'] ?? null,
                'date' => $serviceDate,
                'timezone' => $tz,
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
            ];
        }

        usort($segments, function ($a, $b) {
            $dateCompare = strcmp($a['date'], $b['date']);

            return $dateCompare !== 0 ? $dateCompare : strcmp($a['start_time'], $b['start_time']);
        });

        return $segments;
    }

    protected function resolveServiceable(string $type, int $id): Model
    {
        $serviceable = match ($type) {
            'SubService', 'subservice' => $this->subServiceRepository->find($id),
            'SubServiceItem', 'item' => $this->subServiceItemRepository->find($id),
            default => null,
        };

        if (! $serviceable) {
            throw new HttpResponseException(
                ApiResponse::error(
                    ['serviceType' => __('validation.booking.unknown_service_type')],
                    __('validation.failed'),
                    422
                )
            );
        }

        return $serviceable;
    }

    protected function attachServiceToBookingWithSegment(Booking $booking, array $seg, int $defaultSort): void
    {
        $booking->services()->create([
            'master_id' => $seg['master_id'],
            'is_any_master' => (bool) ($seg['is_any_master'] ?? false),
            'bookable_id' => $seg['bookable_id'],
            'bookable_type' => $seg['bookable_type'],
            'price' => $seg['price'],
            'base_price' => $seg['base_price'] ?? $seg['price'],
            'vat_enabled' => true,
            'vat_rate' => (float) ($seg['vat_rate'] ?? (float) config('vat.rate', 0.05)),
            'vat_amount' => (float) ($seg['vat_amount'] ?? 0),
            'final_price' => $seg['final_price'] ?? $seg['price'],
            'duration_minutes' => $seg['duration_minutes'],
            'sort_order' => $seg['sort_order'] ?? $defaultSort,
            'date' => $seg['date'],
            'timezone' => $seg['timezone'],
            'start_time' => $seg['start_time'],
            'end_time' => $seg['end_time'],
        ]);
    }

    protected function normalizeServicesForPricing(array $services): array
    {
        return collect($services)->values()->map(function ($s) {
            return [
                'price' => (float) ($s['price'] ?? 0),
            ];
        })->all();
    }

    protected function buildPricingData(array $data): array
    {
        $services = collect($data['services'] ?? []);
        $totalPrice = (float) $services->sum(fn ($s) => $s['price'] ?? 0);

        // Only allow admin users to provide custom discount values.
        // For non-admin (public) requests, always calculate discounts server-side
        // based on the user's actual referral/tier status to prevent abuse.
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        if ($isAdmin) {
            $discountType = $data['discount_type'] ?? $data['discountType'] ?? 'none';
            $discountValue = $data['discount_value'] ?? $data['discountValue'] ?? null;
            $discountLabel = $data['discount_label'] ?? $data['discountLabel'] ?? null;

            // Sanitize admin-provided values
            if ($discountValue !== null) {
                $discountValue = max(0, (float) $discountValue);
                if ($discountType === 'percent') {
                    $discountValue = min($discountValue, 100);
                }
            }
        } else {
            // Never trust client-provided discounts for non-admin users
            $discountType = 'none';
            $discountValue = null;
            $discountLabel = null;
        }

        if ($discountType === 'none' || ! $discountValue) {
            $autoDiscount = $this->calculateAutomaticDiscount($services->count());
            if ($autoDiscount['discount_percent'] > 0) {
                $discountType = 'percent';
                $discountValue = $autoDiscount['discount_percent'];
                $discountLabel = $autoDiscount['discount_label'];
            }
        }

        $discountAmount = $this->calculateDiscountAmount(
            totalPrice: $totalPrice,
            discountType: $discountType,
            discountValue: $discountValue !== null ? (float) $discountValue : null
        );

        $finalPrice = max($totalPrice - $discountAmount, 0);

        $paymentMode = $data['payment_mode'] ?? $data['paymentMode'] ?? 'pay_later';

        $paymentStatus = 'unpaid';

        return [
            'total_price' => $totalPrice,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_label' => $discountLabel,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'payment_mode' => $paymentMode,
            'payment_status' => $paymentStatus,
        ];
    }

    protected function calculateAutomaticDiscount(int $serviceCount = 0): array
    {
        $user = auth()->user();

        if ($user && $user->id) {
            $user->loadMissing(['manualReferral', 'referral']);

            $referral = null;
            $bypassVisitCheck = false;

            if ($user->manual_referral_id && $user->manualReferral) {
                $referral = $user->manualReferral;
                $bypassVisitCheck = true;
            } elseif ($user->referral_id && $user->referral) {
                $referral = $user->referral;
            }

            if ($referral && $referral->enabled && $referral->type === 'percentage' && $referral->value > 0) {
                if ($bypassVisitCheck) {
                    return [
                        'discount_percent' => (float) $referral->value,
                        'discount_label' => $referral->name.' Tier Discount',
                    ];
                }

                if ($referral->visit_threshold !== null) {
                    $visitCount = Booking::where('user_id', $user->id)
                        ->where('type', 'booking')
                        ->where('status', '!=', 'cancelled')
                        ->whereIn('payment_status', ['paid', 'gift'])
                        ->count();

                    if ($visitCount >= $referral->visit_threshold) {
                        return [
                            'discount_percent' => (float) $referral->value,
                            'discount_label' => $referral->name.' Tier Discount',
                        ];
                    }
                }
            }
        }

        return [
            'discount_percent' => 0,
            'discount_label' => null,
        ];
    }

    protected function calculateDiscountAmount(float $totalPrice, ?string $discountType, ?float $discountValue): float
    {
        if (! $discountType || $discountType === 'none' || ! $discountValue) {
            return 0.0;
        }

        return match ($discountType) {
            'percent' => round($totalPrice * (min($discountValue, 100) / 100), 2),
            'fixed' => min($discountValue, $totalPrice),
            default => 0.0,
        };
    }

    protected function calculateDuration(string $startTime, string $endTime): int
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        return $start->diffInMinutes($end);
    }

    protected function getWorkingHours(string $bookingDate, string $timezone): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $bookingDate, $timezone);
        $isoDay = $day->dayOfWeekIso;

        $weekday = Weekday::query()->where('day', $isoDay)->first();

        if (! $weekday) {
            return ['is_closed' => true];
        }

        $row = $this->workingHourRepository->findByWeekdayId($weekday->id);

        if (! $row || $row->is_closed) {
            return ['is_closed' => true];
        }

        return [
            'is_closed' => false,
            'start' => substr((string) $row->start_time, 0, 5),
            'end' => substr((string) $row->end_time, 0, 5),
            'break_start' => $row->break_start_time ? substr((string) $row->break_start_time, 0, 5) : null,
            'break_end' => $row->break_end_time ? substr((string) $row->break_end_time, 0, 5) : null,
        ];
    }

    protected function parseTimeToCarbon(string $date, string $time, string $tz): Carbon
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $tz);
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}", $tz);
        }

        $this->throwValidation(
            ['time' => __('validation.booking.invalid_time_format', ['time' => $time])],
            'validation.failed'
        );
    }

    public function cancelBooking(Booking $booking, array $data = []): Booking
    {
        $user = auth()->user();

        if (($booking->type ?? 'booking') !== 'booking') {
            $this->throwValidation([], 'messages.booking.only_bookings_can_be_cancelled');
        }

        if ($booking->status === 'cancelled') {
            return $booking;
        }

        if ($booking->status === 'completed') {
            $this->throwValidation([], 'messages.booking.completed_cannot_be_cancelled');
        }

        $isAdmin = $user?->isAdmin() ?? false;
        if (! $isAdmin) {
            if (! $user) {
                $this->throwError('messages.auth.unauthorized', 401);
            }

            if ((int) $booking->user_id !== (int) $user->id) {
                $this->throwError('messages.booking.cancel_only_own', 403);
            }
        }

        $canRefund = false;
        if ($booking->payment_status === 'paid') {
            $booking->loadMissing('services');
            if ($booking->services->isNotEmpty()) {
                $firstService = $booking->services->sortBy('start_time')->first();
                $date = $booking->date instanceof \Carbon\Carbon ? $booking->date->format('Y-m-d') : (string) $booking->date;
                $startTime = $firstService->start_time ?? $booking->start_time;
                $timezone = $firstService->timezone ?? $booking->timezone ?? 'UTC';

                $timeStr = (string) $startTime;
                if (strlen($timeStr) === 5) {
                    $timeStr .= ':00';
                }

                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeStr}", $timezone);
                $hoursUntilAppointment = now($timezone)->diffInHours($appointmentDateTime, false);

                $canRefund = $hoursUntilAppointment >= 24;
            } else {
                $date = $booking->date instanceof \Carbon\Carbon ? $booking->date->format('Y-m-d') : (string) $booking->date;
                $startTime = $booking->start_time;
                $timezone = $booking->timezone ?? 'UTC';

                $timeStr = (string) $startTime;
                if (strlen($timeStr) === 5) {
                    $timeStr .= ':00';
                }

                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeStr}", $timezone);
                $hoursUntilAppointment = now($timezone)->diffInHours($appointmentDateTime, false);

                $canRefund = $hoursUntilAppointment >= 24;
            }
        }

        $booking->loadMissing('order');
        $order = $booking->order?->load('latestPayment');

        if ($booking->payment_status === 'paid' && $order && $canRefund) {
            // Only card payments go back through Stripe; gift-card / cash / manual
            // bookings have no Stripe payment, so guard the gateway call to avoid
            // aborting the cancellation. orderService->refund() then reverses any
            // gift-card usage and marks the order refunded for every payment type.
            if ($order->latestPayment?->provider === 'stripe') {
                $this->paymentService->refundOrderPayment($order, [
                    'booking_id' => (string) $booking->id,
                    'reason' => 'booking_cancelled',
                ]);
            }
            $this->orderService->refund($order, ['reason' => 'booking_cancelled']);
            $booking->payment_status = 'refunded';
        } elseif ($booking->payment_status === 'paid' && ! $canRefund) {
            // Non-refundable cancellation — keep payment as paid (money retained)
            if ($order) {
                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
            }
        } elseif ($order) {
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        $booking->update([
            'status' => 'cancelled',
            'payment_status' => $booking->payment_status,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user?->id,
            'cancel_reason' => $data['reason'] ?? null,
        ]);

        // Cancel referral if applicable
        $this->referralRewardService->cancelReferral($booking);

        // Reverse package usage if applicable
        if ($booking->is_package_booking) {
            $this->servicePackageService->reverseUsage($booking);
        }

        // Re-check loyalty tier after cancellation (may downgrade)
        if ($booking->user_id) {
            $user = User::find($booking->user_id);
            if ($user) {
                $this->loyaltyService->checkAndUpgradeUser($user);
            }
        }

        return $booking->load(['services.bookable', 'services.master', 'master', 'cancelledBy', 'bookingReferral.referrer'])->refresh();
    }

    public function markBookingPaid(Booking $booking, array $paymentDetails = []): Booking
    {
        if ($booking->type !== 'booking') {
            $this->throwValidation([], 'messages.booking.only_bookings_can_be_marked_paid');
        }

        if ($booking->status === 'cancelled') {
            $this->throwValidation([], 'messages.booking.cancelled_cannot_be_marked_paid');
        }

        // Re-validate slot availability before confirming the booking
        if (!$this->areSlotsStillAvailable($booking)) {
            $this->cancelBookingDueToSlotConflict($booking);
            $this->throwValidation(
                ['slot' => 'The requested time slot is no longer available. The booking has been cancelled and a refund initiated.'],
                'messages.booking.slot_no_longer_available'
            );
        }

        $booking->load('order.latestPayment');

        $updateData = [
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ];

        // Store payment details from admin mark-as-paid
        if (!empty($paymentDetails['paid_payment_method'])) {
            $updateData['paid_payment_method'] = $paymentDetails['paid_payment_method'];
        }
        if (!empty($paymentDetails['gift_card_code'])) {
            $updateData['gift_card_code'] = $paymentDetails['gift_card_code'];
        }
        if (isset($paymentDetails['tip_amount']) && $paymentDetails['tip_amount'] > 0) {
            $updateData['tip_amount'] = $paymentDetails['tip_amount'];
        }

        $booking->update($updateData);

        if ($booking->order) {
            $this->orderService->markPaid($booking->order, ['marked_paid_manually' => true]);

            $payment = $booking->order->latestPayment;

            if ($payment) {
                $paymentUpdate = [
                    'status' => 'paid',
                    'paid_at' => now(),
                ];
                // Admin overrode the method (e.g. a pending Stripe row marked paid by cash);
                // reflect the chosen method instead of leaving the stale gateway provider.
                if (!empty($paymentDetails['paid_payment_method'])) {
                    $paymentUpdate['provider'] = $paymentDetails['paid_payment_method'];
                    $paymentUpdate['flow'] = 'manual';
                }
                $this->paymentRepository->update($payment, $paymentUpdate);
            } else {
                // No Payment record exists (e.g. pay_later bookings). Create one
                // so the turnover dashboard and reports can track this revenue.
                $this->paymentRepository->create([
                    'order_id' => $booking->order->id,
                    'provider' => $paymentDetails['paid_payment_method'] ?? 'manual',
                    'flow' => 'manual',
                    'amount' => $booking->order->amount,
                    'currency' => $booking->order->currency ?? 'AED',
                    'status' => 'paid',
                    'paid_at' => now(),
                    'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
                ]);
            }
        }

        $this->referralRewardService->completeReferral($booking->fresh());

        // Check loyalty tier upgrade after marking paid. markPaid()/resolveOrderCustomer
        // may have back-filled booking.user_id in the DB (guest bookings), so refresh the
        // in-memory instance before reading it — otherwise a guest's visit tier is skipped.
        $booking->refresh();
        if ($booking->user_id) {
            $user = User::find($booking->user_id);
            if ($user) {
                $this->loyaltyService->checkAndUpgradeUser($user);
            }
        }

        return $booking->fresh()->load(['services.bookable', 'services.master', 'master', 'order.latestPayment']);
    }

    /**
     * Mark a booking as a no-show. Per the client data model: money already
     * captured is RETAINED (no refund), the person's Lead/Client status is
     * untouched (forward-only), and the appointment gets its own first-class
     * status. cancel_reason is also set so legacy no-show stats keep counting.
     */
    public function markBookingNoShow(Booking $booking): Booking
    {
        if (($booking->type ?? 'booking') !== 'booking') {
            $this->throwValidation([], 'messages.booking.only_bookings_can_be_cancelled');
        }

        if (in_array($booking->status, ['cancelled', 'completed', 'no_show'], true)) {
            $this->throwValidation(['status' => 'Only an active booking can be marked as a no-show.'], 'messages.booking.only_bookings_can_be_cancelled');
        }

        $booking->update([
            'status' => 'no_show',
            'cancel_reason' => 'no_show',
            'cancelled_at' => now(),
        ]);

        // An unpaid no-show never transacted — its pending order and referral are dropped.
        // A PAID no-show keeps everything: the fee is retained, so the revenue, the
        // completed referral and the loyalty visit all stand.
        if ($booking->payment_status !== 'paid') {
            $this->referralRewardService->cancelReferral($booking);
            $booking->loadMissing('order');
            if ($booking->order && !in_array($booking->order->status, ['paid', 'refunded', 'cancelled'], true)) {
                $booking->order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }
        }

        return $booking->fresh()->load(['services.bookable', 'services.master', 'master', 'order.latestPayment']);
    }

    private function isMasterOffOnDate(int $masterId, string $date, string $tz): bool
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz);
        $isoDay = $day->dayOfWeekIso;

        return User::query()
            ->whereKey($masterId)
            ->masters()
            ->whereHas('weekends', fn ($q) => $q->where('day', $isoDay))
            ->exists();
    }

    protected function clearSelectionsAfterBooking(?int $userId, ?string $guestSessionId): void
    {
        if ($userId) {
            $this->bookingSelectionRepository->deleteByUserId($userId);

            return;
        }

        if ($guestSessionId) {
            $this->bookingSelectionRepository->deleteByGuestSession($guestSessionId);
        }
    }

    protected function makeBookingReference(): string
    {
        return 'BK-'.now()->format('Ymd').'-'.Str::upper(bin2hex(random_bytes(4)));
    }

    protected function makeBatchId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Create multiple bookings from an array of services, sharing a single order/payment.
     * Each service becomes its own booking for cleaner tracking.
     *
     * @param  array  $data  Contains: date, timezone, customerName, customerPhone, customerEmail, notes, paymentMode, services[]
     * @return array{bookings: array, order: \App\Models\Order, batchId: string}
     */
    public function createBatchBookings(array $data): array
    {
        $user = auth()->user();

        $tz = $data['timezone'] ?? 'UTC';
        $date = trim($data['date'] ?? '');

        $rawServices = $data['services'] ?? [];
        if (! is_array($rawServices) || count($rawServices) === 0) {
            $this->throwValidation(
                ['services' => __('validation.booking.services_required')],
                'validation.failed'
            );
        }

        $rawServices = $this->masterAssignmentService->assignAndValidateMasters(
            date: $date,
            tz: $tz,
            services: $rawServices
        );

        $segments = $this->buildServiceSegmentsFromRequest($date, $rawServices, $tz);

        $pricing = $this->buildPricingData([
            ...$data,
            'services' => $this->normalizeServicesForPricing($segments),
        ]);

        return DB::transaction(function () use ($data, $user, $tz, $segments, $pricing) {
            $this->assertSegmentsAvailable($segments, $tz);

            $batchId = $this->makeBatchId();
            $bookings = [];
            $totalPrice = 0;
            $totalFinalPrice = 0;

            foreach ($segments as $index => $seg) {
                $segmentStart = substr($seg['start_time'], 0, 5);
                $segmentEnd = substr($seg['end_time'], 0, 5);

                $bookingData = [
                    'user_id' => $user?->id,
                    'type' => 'booking',
                    'reference' => $this->makeBookingReference(),
                    'batch_id' => $batchId,
                    'date' => $seg['date'],
                    'timezone' => $tz,
                    'start_time' => $segmentStart,
                    'end_time' => $segmentEnd,
                    'duration' => $seg['duration_minutes'],
                    'duration_unit' => 'minutes',
                    'price' => $seg['price'],
                    'discount_type' => $pricing['discount_type'],
                    'discount_value' => $pricing['discount_value'],
                    'discount_label' => $pricing['discount_label'],
                    'final_price' => $seg['final_price'],
                    'payment_mode' => $pricing['payment_mode'],
                    'payment_status' => $pricing['payment_status'],
                    'status' => $pricing['payment_mode'] === 'pay_now' ? 'pending_payment' : 'confirmed',
                    'expires_at' => $pricing['payment_mode'] === 'pay_now'
                        ? now()->addMinutes((int) config('payment.booking_hold_minutes', 10))
                        : null,
                    'customer_name' => $data['customer_name'] ?? $data['customerName'],
                    'customer_phone' => $data['customer_phone'] ?? $data['customerPhone'],
                    'customer_email' => $data['customer_email'] ?? $data['customerEmail'],
                    'notes' => $data['notes'] ?? null,
                    'master_id' => (int) $seg['master_id'],
                ];

                $booking = $this->bookingRepository->create($bookingData);

                $this->attachServiceToBookingWithSegment($booking, $seg, 1);

                $totalPrice += $seg['price'];
                $totalFinalPrice += $seg['final_price'];
                $bookings[] = $booking;
            }

            $this->clearSelectionsAfterBooking($user?->id, $data['guest_session_id'] ?? null);

            if (! empty($bookings)) {
                $this->createLeadFromBooking($bookings[0]);
            }

            $primaryBooking = $bookings[0];

            $originalPrice = $primaryBooking->price;
            $originalFinalPrice = $primaryBooking->final_price;
            $primaryBooking->price = $totalPrice;
            $primaryBooking->final_price = $pricing['final_price'];

            $order = $this->orderService->createForBooking($primaryBooking, $primaryBooking->payment_mode);

            $primaryBooking->price = $originalPrice;
            $primaryBooking->final_price = $originalFinalPrice;

            if ($primaryBooking->payment_mode === 'pay_later') {
                foreach ($bookings as $booking) {
                    $this->bookingRepository->update($booking, ['status' => 'confirmed']);
                }

                foreach ($bookings as $booking) {
                    $booking->refresh()->load(['services.bookable', 'master']);
                }

                return [
                    'bookings' => $bookings,
                    'order' => $order,
                    'batchId' => $batchId,
                ];
            }

            // Handle gift card for pay_now batch bookings
            $giftCardCode = $data['gift_card_code'] ?? $data['giftCardCode'] ?? null;
            if ($giftCardCode) {
                $purchase = \App\Models\GiftCardPurchase::where('code', $giftCardCode)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($purchase && !$purchase->isExpired() && $purchase->balance > 0) {
                    $total = (float) $order->amount;
                    $giftCardAmountApplied = min((float) $purchase->balance, $total);
                    $newBalance = max(0, (float) $purchase->balance - $giftCardAmountApplied);

                    $purchase->update([
                        'balance' => $newBalance,
                        ...($newBalance <= 0 ? ['status' => 'used'] : []),
                    ]);

                    \App\Models\GiftCardUsage::create([
                        'gift_card_purchase_id' => $purchase->id,
                        'amount_used' => $giftCardAmountApplied,
                        'used_for_type' => 'booking',
                        'used_for_id' => $primaryBooking->id,
                        'used_for_name' => 'Batch Booking #' . $batchId,
                        'used_for' => 'booking',
                        'notes' => 'Applied at online booking checkout (batch)',
                        'verified_by' => $user?->id,
                    ]);

                    $order->update([
                        'meta' => array_merge($order->meta ?? [], [
                            'gift_card_code' => $giftCardCode,
                            'gift_card_amount' => $giftCardAmountApplied,
                        ]),
                    ]);

                    if ($purchase->buyer_email) {
                        \Illuminate\Support\Facades\Mail::to($purchase->buyer_email)
                            ->queue(new \App\Mail\GiftCardBalanceDeductedMail($purchase, $giftCardAmountApplied));
                    }

                    if ($giftCardAmountApplied >= $total) {
                        $order->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'amount' => 0,
                        ]);

                        $this->markBatchBookingsPaid($batchId);

                        foreach ($bookings as $booking) {
                            $this->sendBookingConfirmation($booking);
                        }

                        foreach ($bookings as $booking) {
                            $booking->refresh()->load(['services.bookable', 'services.master', 'master']);
                        }

                        return [
                            'bookings' => $bookings,
                            'order' => $order->fresh(['latestPayment']),
                            'batchId' => $batchId,
                        ];
                    }

                    $remainingAmount = $total - $giftCardAmountApplied;
                    $order->update(['amount' => $remainingAmount]);
                }
            }

            $this->paymentService->startStripePaymentIntent($order, $primaryBooking);

            foreach ($bookings as $booking) {
                $booking->load(['services.bookable', 'services.master', 'master']);
            }
            $primaryBooking->load('order.latestPayment');

            return [
                'bookings' => $bookings,
                'order' => $order->fresh(['latestPayment']),
                'batchId' => $batchId,
            ];
        });
    }

    /**
     * Mark all bookings in a batch as paid.
     * Re-validates slot availability for each booking before confirming.
     */
    public function markBatchBookingsPaid(string $batchId): void
    {
        $bookings = Booking::where('batch_id', $batchId)->get();

        foreach ($bookings as $booking) {
            if ($booking->payment_status !== 'paid') {
                // Re-validate slot availability before confirming each booking
                if (!$this->areSlotsStillAvailable($booking)) {
                    $this->cancelBookingDueToSlotConflict($booking);
                    continue;
                }

                $booking->update([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                ]);
            }
        }
    }

    /**
     * Get all bookings in a batch.
     */
    public function getBookingsByBatchId(string $batchId): Collection
    {
        return Booking::where('batch_id', $batchId)
            ->with(['services.bookable', 'services.master', 'master'])
            ->get();
    }

    protected function createLeadFromBooking(Booking $booking): void
    {
        if ($booking->user_id) {
            return;
        }

        if (! $booking->customer_email && ! $booking->customer_phone) {
            return;
        }

        $this->customerService->resolveForTransaction([
            'name' => $booking->customer_name,
            'email' => $booking->customer_email,
            'phone' => $booking->customer_phone,
            'source' => 'booking',
        ]);
    }

    protected function assertSegmentsAvailable(array $segments, string $tz, ?int $excludeBookingId = null): void
    {
        foreach ($segments as $seg) {
            $masterId = (int) ($seg['master_id'] ?? 0);
            if ($masterId <= 0) {
                continue;
            }

            $hasOverlap = $this->bookingRepository->hasOverlap(
                $masterId,
                (string) $seg['date'],
                (string) $seg['start_time'],
                (string) $seg['end_time'],
                $excludeBookingId,
                $seg['timezone'] ?? $tz,
                true
            );

            if ($hasOverlap) {
                $this->throwValidation(
                    ['date' => __('validation.booking.master_unavailable')],
                    'validation.failed'
                );
            }
        }
    }

    protected function throwValidation(array $errors, string $messageKey, array $replace = [], int $status = 422): void
    {
        throw new HttpResponseException(
            ApiResponse::error($errors, __($messageKey, $replace), $status)
        );
    }

    protected function throwError(string $messageKey, int $status, array $errors = [], array $replace = []): void
    {
        throw new HttpResponseException(
            ApiResponse::error($errors ?: null, __($messageKey, $replace), $status)
        );
    }

    public function sendBookingConfirmation(Booking $booking): void
    {
        $email = $booking->customer_email;

        if ($email) {
            Mail::to($email)->queue(new BookingConfirmedMail($booking));
        }

        $this->sendAdminBookingNotification($booking, 'new');

        // Complete referral when booking is confirmed/paid
        $this->referralRewardService->completeReferral($booking);

        // Check loyalty tier upgrade after booking confirmation
        if ($booking->user_id) {
            $user = User::find($booking->user_id);
            if ($user) {
                $this->loyaltyService->checkAndUpgradeUser($user);
            }
        }
    }

    public function sendBookingCancellation(Booking $booking, ?string $reason = null): void
    {
        $email = $booking->customer_email;

        if ($email) {
            Mail::to($email)->queue(new BookingCancelledMail($booking));
        }

        $this->sendAdminBookingNotification($booking, 'cancelled', $reason);
    }

    public function sendBookingRescheduled(
        Booking $booking,
        ?string $previousDate = null,
        ?string $previousStartTime = null,
        ?string $previousEndTime = null
    ): void {
        $email = $booking->customer_email;

        if ($email) {
            Mail::to($email)->queue(new BookingRescheduledMail(
                $booking,
                $previousDate,
                $previousStartTime,
                $previousEndTime
            ));
        }

        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'superadmin'))->first();
        if ($admin && $admin->email) {
            Mail::to($admin->email)->queue(new BookingRescheduledAdminNotificationMail(
                $booking,
                $previousDate,
                $previousStartTime,
                $previousEndTime
            ));
        }
    }

    protected function sendAdminBookingNotification(Booking $booking, string $type, ?string $reason = null): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('slug', 'superadmin'))->first();

        if (! $admin || ! $admin->email) {
            return;
        }

        $mail = match ($type) {
            'new' => new NewBookingAdminNotificationMail($booking),
            'cancelled' => new BookingCancelledAdminNotificationMail($booking, $reason),
            default => null,
        };

        if ($mail) {
            Mail::to($admin->email)->queue($mail);
        }
    }

    /**
     * Re-validate that all service slots for a booking are still available.
     * Used before confirming payment to prevent double-bookings if the slot
     * was taken or the master's schedule changed after payment was initiated.
     *
     * @return bool true if all slots are still available
     */
    public function areSlotsStillAvailable(Booking $booking): bool
    {
        $booking->loadMissing('services');

        foreach ($booking->services as $service) {
            $bookableType = $service->bookable_type;
            $bookableId = $service->bookable_id;
            $date = is_string($service->date) ? $service->date : $service->date->toDateString();
            $startTime = (string) $service->start_time;
            $endTime = (string) $service->end_time;
            $tz = $service->timezone ?? $booking->timezone ?? 'UTC';

            if (!$bookableType || !$bookableId) {
                continue;
            }

            $hasServiceOverlap = $this->bookingRepository->hasServiceOverlap(
                bookableType: $bookableType,
                bookableId: $bookableId,
                date: $date,
                startTime: $startTime,
                endTime: $endTime,
                excludeBookingId: $booking->id,
                timezone: $tz
            );

            if ($hasServiceOverlap) {
                return false;
            }

            $masterId = $service->master_id;
            if ($masterId) {
                $hasMasterOverlap = $this->bookingRepository->hasOverlap(
                    masterId: $masterId,
                    date: $date,
                    startTime: $startTime,
                    endTime: $endTime,
                    excludeBookingId: $booking->id,
                    timezone: $tz
                );

                if ($hasMasterOverlap) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Cancel a booking due to slot conflict at payment confirmation time
     * and initiate a refund if a payment exists.
     */
    public function cancelBookingDueToSlotConflict(Booking $booking): void
    {
        \Log::critical('[booking][slot-conflict] Slot no longer available at payment confirmation', [
            'booking_id' => $booking->id,
            'reference' => $booking->reference,
            'date' => $booking->date,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'master_id' => $booking->master_id,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
        ]);

        $this->bookingRepository->update($booking, [
            'status' => 'cancelled',
            'cancel_reason' => 'Slot no longer available at time of payment confirmation. Automatic refund initiated.',
            'cancelled_at' => now(),
        ]);

        $booking->loadMissing('order.latestPayment');
        if ($booking->order && $booking->order->latestPayment) {
            try {
                $this->paymentService->refundOrderPayment($booking->order, [
                    'reason' => 'slot_conflict',
                    'booking_id' => $booking->id,
                ]);
                \Log::info('[booking][slot-conflict] Refund initiated successfully', [
                    'booking_id' => $booking->id,
                    'order_id' => $booking->order->id,
                ]);
            } catch (\Throwable $e) {
                \Log::error('[booking][slot-conflict] Failed to initiate refund', [
                    'booking_id' => $booking->id,
                    'order_id' => $booking->order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->sendBookingCancellation($booking, 'Slot was no longer available when payment was confirmed.');
    }
}
