<?php

namespace App\Http\Resources;

use App\Models\SubService;
use App\Models\SubServiceItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property mixed $master
 * @property mixed $date
 * @property mixed $start_time
 * @property mixed $end_time
 * @property mixed $type
 * @property mixed $status
 * @property mixed $total_price
 * @property mixed $final_price
 * @property mixed $notes
 * @property mixed $services
 * @property mixed $price
 * @property mixed $discount_value
 * @property mixed $discount_type
 * @property mixed $timezone
 * @property mixed $discount_label
 * @property mixed $cancelledBy
 * @property mixed $cancelled_at
 * @property mixed $cancel_reason
 * @method relationLoaded(string $string)
 * @property mixed $order
 */
class BookingResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $user = $request->user();
        $isAdmin = $user?->isAdmin() ?? false;

        $services = $this->services ?? collect();
        $overallStart = $this->start_time;
        $overallEnd   = $this->end_time;

        if ($services->count() > 0 && $services->first()?->start_time && $services->last()?->end_time) {
            $sorted = $services->sortBy('start_time')->values();
            $overallStart = substr((string) $sorted->first()->start_time, 0, 5);
            $overallEnd   = substr((string) $sorted->last()->end_time, 0, 5);
        }
        $baseTotal = (float) $services->sum(fn ($s) => (float) ($s->base_price ?? 0));
        $vatTotal  = (float) $services->sum(fn ($s) => (float) ($s->vat_amount ?? 0));
        $finalTotalFromLines = (float) $services->sum(fn ($s) => (float) ($s->final_price ?? $s->price ?? 0));

        $canCancel = false;
        $canRefund = false;
        $refundDeadline = null;

        if ($this->status !== 'cancelled' && $this->status !== 'completed') {
            $canCancel = true;
            
            if ($services->isNotEmpty()) {
                $firstService = $services->sortBy('start_time')->first();
                $date = $this->date instanceof \Carbon\Carbon ? $this->date->format('Y-m-d') : (string) $this->date;
                $startTime = $firstService->start_time ?? $this->start_time;
                $timezone = $firstService->timezone ?? $this->timezone ?? 'UTC';
                
                $timeStr = (string) $startTime;
                if (strlen($timeStr) === 5) {
                    $timeStr .= ':00';
                }
                
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeStr}", $timezone);
                $refundDeadline = $appointmentDateTime->copy()->subHours(24);
                $hoursUntilAppointment = now($timezone)->diffInHours($appointmentDateTime, false);
                
                $canRefund = $hoursUntilAppointment >= 24 && $this->payment_status === 'paid';
            } elseif ($this->start_time) {
                $date = $this->date instanceof \Carbon\Carbon ? $this->date->format('Y-m-d') : (string) $this->date;
                $startTime = $this->start_time;
                $timezone = $this->timezone ?? 'UTC';
                
                $timeStr = (string) $startTime;
                if (strlen($timeStr) === 5) {
                    $timeStr .= ':00';
                }
                
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeStr}", $timezone);
                $refundDeadline = $appointmentDateTime->copy()->subHours(24);
                $hoursUntilAppointment = now($timezone)->diffInHours($appointmentDateTime, false);
                
                $canRefund = $hoursUntilAppointment >= 24 && $this->payment_status === 'paid';
            }
        }

        return [
            'id'            => $data['id'] ?? null,
            'reference'     => $this->reference ?? "#{$this->id}",
            'batchId'       => $this->batch_id ?? null,
            'customerName'  => $data['customer_name'] ?? null,
            'customerEmail' => $data['customer_email'] ?? null,
            'customerPhone' => $data['customer_phone'] ?? null,
            'date'          => $this->date,
            'timezone'      => $this->timezone ?? null,
            'startTime'     => $overallStart,
            'endTime'       => $overallEnd,
            'type'          => $this->type,
            'status'        => $this->status,
            'paymentStatus' => $data['payment_status'] ?? null,
            'paidPaymentMethod' => $this->paid_payment_method,
            'giftCardCode'  => $this->gift_card_code,
            'tipAmount'     => $this->tip_amount ? (float) $this->tip_amount : 0,
            'cancelledBy'   => $this->when($this->cancelledBy, new UserResource($this->cancelledBy)),
            'cancelledAt'   => $this->cancelled_at,
            'cancelReason'  => $this->cancel_reason,
            'price'         => $this->price,
            'discountType'  => $this->discount_type,
            'discountValue' => $this->discount_value,
            'discountLabel' => $this->discount_label,
            'totalPrice'    => $this->final_price,
            'vat' => [
                'rate'                 => (float) config('vat.rate', 0.05),
                'baseTotal'            => round($baseTotal, 2),
                'vatTotal'             => round($vatTotal, 2),
                'finalTotalFromLines'  => round($finalTotalFromLines, 2),
            ],
            'order' => $this->whenLoaded('order', function () {
                return new OrderResource($this->order->loadMissing('latestPayment'));
            }),
            'notes' => $this->notes,
            'services' => $services->map(function ($bs) use ($isAdmin) {
                $bookable = $bs->bookable;
                $serviceType = match (true) {
                    $bookable instanceof SubService => 'subservice',
                    $bookable instanceof SubServiceItem => 'item',
                    default => null,
                };
                $isAny = (bool) ($bs->is_any_master ?? false);
                $canSeeMaster = $isAdmin || !$isAny;
                $basePrice  = (float) ($bs->base_price ?? 0);
                $vatEnabled = (bool)  ($bs->vat_enabled ?? false);
                $vatRate    = (float) ($bs->vat_rate ?? (float) config('vat.rate', 0.05));
                $vatAmount  = (float) ($bs->vat_amount ?? 0);
                $finalPrice = (float) ($bs->final_price ?? $bs->price ?? 0);

                // For variants, show "Parent - Variant" so receipts/calendars
                // identify the booked service, not just the variant label.
                $displayName = $bookable?->name;
                $parentName = null;
                if ($bookable instanceof SubServiceItem) {
                    $bookable->loadMissing('subService');
                    $parentName = $bookable->subService?->name;
                    if ($parentName) {
                        $displayName = $parentName . ' - ' . $bookable->name;
                    }
                }

                return [
                    'id'          => $bs->id,
                    'serviceType' => $serviceType,
                    'serviceId'   => $bookable?->id,
                    'name'        => $displayName,
                    'parentName'  => $parentName,
                    'variantName' => $bookable instanceof SubServiceItem ? $bookable->name : null,
                    'pricing' => [
                        'basePrice'  => round($basePrice, 2),
                        'vatEnabled' => $vatEnabled,
                        'vatRate'    => $vatEnabled ? $vatRate : 0.0,
                        'vatAmount'  => round($vatAmount, 2),
                        'finalPrice' => round($finalPrice, 2),
                    ],
                    'price'       => $bs->price,
                    'duration'    => $bs->duration_minutes,
                    'date'        => $bs->date ?? $this->date,
                    'timezone'    => $bs->timezone ?? $this->timezone,
                    'startTime'   => $bs->start_time ? substr((string) $bs->start_time, 0, 5) : null,
                    'endTime'     => $bs->end_time ? substr((string) $bs->end_time, 0, 5) : null,
                    'isAnyMaster' => $isAny,

                    'master' => $this->when(
                        $canSeeMaster && $bs->relationLoaded('master') && $bs->master,
                        new StaffResource($bs->master)
                    ),
                ];
            })->values(),

            'master' => $this->when(
                $isAdmin && $this->relationLoaded('master') && $this->master,
                new StaffResource($this->master)
            ),
            'isComplimentary' => (bool) ($this->is_complimentary ?? false),
            'referrer' => $this->when(
                $this->relationLoaded('bookingReferral') && $this->bookingReferral,
                function () {
                    $referrer = $this->bookingReferral->referrer ?? null;
                    return $referrer ? [
                        'id' => $referrer->id,
                        'name' => $referrer->name ?? trim(($referrer->first_name ?? '') . ' ' . ($referrer->last_name ?? '')) ?: null,
                    ] : null;
                }
            ),
            'cancellation' => [
                'canCancel' => $canCancel,
                'canRefund' => $canRefund,
                'refundDeadline' => $refundDeadline?->toIso8601String(),
                'message' => $canRefund 
                    ? 'Cancellation with refund is available up to 24 hours before the appointment.'
                    : ($canCancel && $this->payment_status === 'paid'
                        ? 'Cancellation is available, but refund is only available if cancelled at least 24 hours before the appointment.'
                        : null),
            ],
        ];
    }
}



